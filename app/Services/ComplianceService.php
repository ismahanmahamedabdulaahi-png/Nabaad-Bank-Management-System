<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ComplianceCase;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ComplianceCaseOpened;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ComplianceService
{
    public function largeTransactionThreshold(): float
    {
        return (float) (DB::table('settings')->where('key', 'aml_large_txn_threshold')->value('value') ?? 10000);
    }

    public function flagManually(Customer $customer, ?Transaction $transaction, string $notes, int $staffId, string $severity = 'medium'): ComplianceCase
    {
        $case = $this->open($customer, $transaction, 'manual_flag', $severity, $notes, $staffId);

        AuditLog::record('compliance.flagged', 'compliance_cases',
            "Compliance case #{$case->id} opened for customer {$customer->name} (manual flag).");

        return $case;
    }

    // Every completed transaction at/above the threshold in a date range —
    // regulatory-style reporting of large transactions, not an accusation.
    // No case is created; this is a report a compliance officer runs and reviews.
    public function largeTransactions(string $from, string $to)
    {
        $threshold = $this->largeTransactionThreshold();

        return Transaction::with(['account.customer'])
            ->where('status', 'completed')
            ->where('amount', '>=', $threshold)
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->orderByDesc('amount')
            ->get();
    }

    // Flags a customer with 3+ transactions in a trailing 24h window that are
    // each individually under the reporting threshold but sum above it — a
    // classic structuring ("smurfing") pattern. Deliberately conservative
    // (90% of threshold, 3+ transactions) to avoid flagging ordinary activity.
    public function detectStructuring(): int
    {
        $threshold = $this->largeTransactionThreshold();
        $floor     = $threshold * 0.9;
        $since     = now()->subDay();

        $candidates = Transaction::where('status', 'completed')
            ->whereIn('type', ['deposit', 'withdrawal'])
            ->where('amount', '<', $threshold)
            ->where('amount', '>=', $floor)
            ->where('created_at', '>=', $since)
            ->with('account.customer')
            ->get()
            ->groupBy(fn ($t) => $t->account->customer_id);

        $opened = 0;

        foreach ($candidates as $customerId => $transactions) {
            if ($transactions->count() < 3) {
                continue;
            }
            if ($transactions->sum('amount') < $threshold) {
                continue;
            }

            // Don't re-open a case for the same customer if one from this
            // detection type is already active.
            $alreadyOpen = ComplianceCase::where('customer_id', $customerId)
                ->where('type', 'structuring')
                ->whereIn('status', ['open', 'reviewing'])
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            $customer = $transactions->first()->account->customer;
            $this->open($customer, null, 'structuring', 'medium',
                "System-detected: {$transactions->count()} transactions totalling " .
                number_format((float) $transactions->sum('amount'), 2) .
                " within 24 hours, each under the reporting threshold.", null);

            $opened++;
        }

        return $opened;
    }

    private function open(Customer $customer, ?Transaction $transaction, string $type, string $severity, string $notes, ?int $staffId): ComplianceCase
    {
        $case = ComplianceCase::create([
            'customer_id'    => $customer->id,
            'transaction_id' => $transaction?->id,
            'type'           => $type,
            'severity'       => $severity,
            'status'         => 'open',
            'notes'          => $notes,
            'flagged_by'     => $staffId,
        ]);

        $recipients = User::permission('compliance.manage')->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ComplianceCaseOpened($case->load('customer')));
        }

        return $case;
    }

    public function updateCase(ComplianceCase $case, string $status, ?string $notes): ComplianceCase
    {
        $case->update([
            'status' => $status,
            'notes'  => $notes ?? $case->notes,
        ]);

        AuditLog::record('compliance.updated', 'compliance_cases', "Compliance case #{$case->id} marked {$status}.");

        return $case->fresh();
    }
}
