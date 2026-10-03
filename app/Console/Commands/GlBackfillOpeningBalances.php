<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Cheque;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\TellerTill;
use App\Models\Vault;
use App\Services\GeneralLedgerService;
use Illuminate\Console\Command;

class GlBackfillOpeningBalances extends Command
{
    protected $signature   = 'gl:backfill-opening-balances';
    protected $description = 'One-time: post an opening journal entry so the General Ledger reconciles with pre-existing account/vault/till/loan/cheque balances.';

    public function handle(GeneralLedgerService $gl): int
    {
        if (JournalEntry::exists()) {
            $this->error('The General Ledger already has journal entries. Refusing to backfill opening balances a second time.');
            return Command::FAILURE;
        }

        $vaultBalance    = (float) Vault::sum('balance');
        $tillBalance     = (float) TellerTill::sum('current_balance');
        $loansReceivable = (float) Loan::whereIn('status', ['active', 'overdue'])->sum('outstanding_balance');
        $savings         = (float) Account::where('account_type', 'savings')->sum('balance');
        $current         = (float) Account::where('account_type', 'current')->sum('balance');
        $fixedDeposits   = (float) Account::where('account_type', 'fixed_deposit')->sum('balance');
        $chequesClearing = (float) Cheque::where('status', 'pending_clearance')->sum('amount');

        $debitTotal  = $vaultBalance + $tillBalance + $loansReceivable;
        $creditTotal = $savings + $current + $fixedDeposits + $chequesClearing;
        $plug        = round($debitTotal - $creditTotal, 2);

        $lines = array_filter([
            $vaultBalance    > 0 ? ['account' => '1000', 'debit'  => $vaultBalance] : null,
            $tillBalance     > 0 ? ['account' => '1010', 'debit'  => $tillBalance] : null,
            $loansReceivable > 0 ? ['account' => '1100', 'debit'  => $loansReceivable] : null,
            $savings         > 0 ? ['account' => '2000', 'credit' => $savings] : null,
            $current         > 0 ? ['account' => '2010', 'credit' => $current] : null,
            $fixedDeposits   > 0 ? ['account' => '2020', 'credit' => $fixedDeposits] : null,
            $chequesClearing > 0 ? ['account' => '2100', 'credit' => $chequesClearing] : null,
        ]);

        if (abs($plug) > 0.005) {
            $lines[] = $plug > 0
                ? ['account' => '3000', 'credit' => abs($plug)]
                : ['account' => '3000', 'debit'  => abs($plug)];
        }

        if (count($lines) < 2) {
            $this->info('Nothing to backfill — no pre-existing balances found.');
            return Command::SUCCESS;
        }

        $entry = $gl->post($lines, 'Opening balances at General Ledger go-live', 'opening_balance', null, today());

        $this->info("Posted opening journal entry {$entry->entry_number}.");
        $this->table(['GL Account', 'Debit', 'Credit'], $entry->lines->map(fn ($l) => [
            $l->glAccount->code . ' — ' . $l->glAccount->name,
            $l->debit > 0 ? number_format($l->debit, 2) : '',
            $l->credit > 0 ? number_format($l->credit, 2) : '',
        ])->all());

        return Command::SUCCESS;
    }
}
