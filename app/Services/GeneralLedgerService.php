<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Cheque;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Loan;
use App\Models\TellerTill;
use App\Models\Vault;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GeneralLedgerService
{
    // Maps Account::account_type -> GL control account code.
    private const DEPOSIT_ACCOUNT_CODES = [
        'savings'       => '2000',
        'current'       => '2010',
        'fixed_deposit' => '2020',
    ];

    /**
     * Post a balanced journal entry.
     *
     * @param  array<int, array{account: string, debit?: float, credit?: float, memo?: string}>  $lines
     */
    public function post(
        array $lines,
        string $description,
        string $sourceType,
        string|int|null $sourceId = null,
        ?Carbon $date = null,
        ?int $branchId = null,
    ): JournalEntry {
        $lines = array_values(array_filter($lines, function ($line) {
            $debit  = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            return $debit > 0 || $credit > 0;
        }));

        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two non-zero lines.');
        }

        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit  = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('A journal entry line cannot have both a debit and a credit.');
            }

            $totalDebit  += $debit;
            $totalCredit += $credit;
        }

        if (abs($totalDebit - $totalCredit) > 0.005) {
            throw new InvalidArgumentException(
                "Journal entry does not balance: debits {$totalDebit} != credits {$totalCredit}."
            );
        }

        return DB::transaction(function () use ($lines, $description, $sourceType, $sourceId, $date, $branchId) {
            $entry = JournalEntry::create([
                'entry_number' => $this->generateEntryNumber(),
                'entry_date'   => ($date ?? now())->toDateString(),
                'description'  => $description,
                'source_type'  => $sourceType,
                'source_id'    => $sourceId !== null ? (string) $sourceId : null,
                'branch_id'    => $branchId,
                'created_by'   => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $glAccount = $line['account'] instanceof GlAccount
                    ? $line['account']
                    : $this->accountByCode($line['account']);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'gl_account_id'    => $glAccount->id,
                    'debit'            => round((float) ($line['debit'] ?? 0), 2),
                    'credit'           => round((float) ($line['credit'] ?? 0), 2),
                    'memo'             => $line['memo'] ?? null,
                ]);
            }

            return $entry->fresh('lines.glAccount');
        });
    }

    // Post the mirror-image (debits <-> credits) of an existing entry.
    public function reverse(JournalEntry $entry, string $reason, ?string $sourceType = null, string|int|null $sourceId = null): JournalEntry
    {
        $lines = $entry->lines->map(fn (JournalEntryLine $line) => [
            'account' => $line->glAccount,
            'debit'   => (float) $line->credit,
            'credit'  => (float) $line->debit,
            'memo'    => $reason,
        ])->all();

        $reversal = $this->post(
            $lines,
            "Reversal of {$entry->entry_number}: {$reason}",
            $sourceType ?? $entry->source_type,
            $sourceId ?? $entry->source_id,
        );

        $reversal->update(['is_reversal' => true, 'reversed_journal_entry_id' => $entry->id]);

        return $reversal->fresh('lines.glAccount');
    }

    public function depositAccountFor(Account $account): GlAccount
    {
        $code = self::DEPOSIT_ACCOUNT_CODES[$account->account_type] ?? null;

        if (!$code) {
            throw new InvalidArgumentException("No GL deposit control account mapped for account type '{$account->account_type}'.");
        }

        return $this->accountByCode($code);
    }

    // Cash control account for a teller till if one is open. Falling back to the vault
    // (1000) here would be wrong — no till means nothing actually moved through the
    // Vault model, so that would permanently break vault reconciliation. Instead this
    // books to a dedicated off-till suspense account, which is only ever expected to be
    // nonzero when a deposit/withdrawal was posted by someone without an open till.
    public function cashAccountFor(?TellerTill $till): GlAccount
    {
        return $this->accountByCode($till ? '1010' : '1020');
    }

    public function accountByCode(string $code): GlAccount
    {
        static $cache = [];

        if (!isset($cache[$code])) {
            $account = GlAccount::where('code', $code)->where('is_active', true)->first();

            if (!$account) {
                throw new InvalidArgumentException("GL account '{$code}' not found or inactive.");
            }

            $cache[$code] = $account;
        }

        return $cache[$code];
    }

    // ── Reports ───────────────────────────────────────────────────────────────

    public function trialBalance(?string $asOf = null)
    {
        return GlAccount::orderBy('code')->get()->map(function (GlAccount $account) use ($asOf) {
            $query = JournalEntryLine::where('gl_account_id', $account->id);
            if ($asOf) {
                $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf));
            }
            $totals = $query->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')->first();

            return [
                'code'         => $account->code,
                'name'         => $account->name,
                'type'         => $account->type,
                'debit_total'  => round((float) $totals->debit_total, 2),
                'credit_total' => round((float) $totals->credit_total, 2),
                'balance'      => round($account->isDebitNormal()
                    ? (float) $totals->debit_total - (float) $totals->credit_total
                    : (float) $totals->credit_total - (float) $totals->debit_total, 2),
            ];
        });
    }

    public function balanceSheet(?string $asOf = null): array
    {
        $rows = $this->trialBalance($asOf);

        return [
            'assets'      => $rows->where('type', 'asset')->values(),
            'liabilities' => $rows->where('type', 'liability')->values(),
            'equity'      => $rows->where('type', 'equity')->values(),
            'total_assets'      => round($rows->where('type', 'asset')->sum('balance'), 2),
            'total_liabilities' => round($rows->where('type', 'liability')->sum('balance'), 2),
            'total_equity'      => round($rows->where('type', 'equity')->sum('balance'), 2),
        ];
    }

    public function incomeStatement(string $from, string $to): array
    {
        $rows = GlAccount::whereIn('type', ['income', 'expense'])->orderBy('code')->get()->map(function (GlAccount $account) use ($from, $to) {
            $totals = JournalEntryLine::where('gl_account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
                ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
                ->first();

            return [
                'code'    => $account->code,
                'name'    => $account->name,
                'type'    => $account->type,
                'balance' => round($account->isDebitNormal()
                    ? (float) $totals->debit_total - (float) $totals->credit_total
                    : (float) $totals->credit_total - (float) $totals->debit_total, 2),
            ];
        });

        $income  = $rows->where('type', 'income');
        $expense = $rows->where('type', 'expense');

        return [
            'income'       => $income->values(),
            'expense'      => $expense->values(),
            'total_income' => round($income->sum('balance'), 2),
            'total_expense'=> round($expense->sum('balance'), 2),
            'net_income'   => round($income->sum('balance') - $expense->sum('balance'), 2),
        ];
    }

    // Compares each control account's GL balance against its subsidiary-table total.
    // Returns only the accounts that don't tie out.
    public function reconciliationReport(): array
    {
        $checks = [
            '1000' => fn () => (float) (Vault::sum('balance')),
            '1010' => fn () => (float) (TellerTill::sum('current_balance')),
            '1100' => fn () => (float) (Loan::whereIn('status', ['active', 'overdue'])->sum('outstanding_balance')),
            '2000' => fn () => (float) (Account::where('account_type', 'savings')->sum('balance')),
            '2010' => fn () => (float) (Account::where('account_type', 'current')->sum('balance')),
            '2020' => fn () => (float) (Account::where('account_type', 'fixed_deposit')->sum('balance')),
            '2100' => fn () => (float) (Cheque::where('status', 'pending_clearance')->sum('amount')),
        ];

        $mismatches = [];

        foreach ($checks as $code => $expectedFn) {
            $glAccount = GlAccount::where('code', $code)->first();
            if (!$glAccount) {
                continue;
            }

            $glBalance = $glAccount->balance();
            $expected  = round($expectedFn(), 2);

            if (abs($glBalance - $expected) > 0.01) {
                $mismatches[] = [
                    'code'        => $code,
                    'name'        => $glAccount->name,
                    'gl_balance'  => $glBalance,
                    'expected'    => $expected,
                    'difference'  => round($glBalance - $expected, 2),
                ];
            }
        }

        return $mismatches;
    }

    private function generateEntryNumber(): string
    {
        $date = now()->format('Ymd');
        $key  = "gl_entry_seq_{$date}";

        $setting = DB::table('settings')->where('key', $key)->lockForUpdate()->first();

        if (!$setting) {
            $next = 1;
            DB::table('settings')->insert([
                'key' => $key, 'value' => '1', 'group' => 'system',
                'label' => "GL Entry Sequence {$date}", 'type' => 'integer',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $next = (int) $setting->value + 1;
            DB::table('settings')->where('key', $key)->update([
                'value' => (string) $next, 'updated_at' => now(),
            ]);
        }

        return 'JE-' . $date . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
