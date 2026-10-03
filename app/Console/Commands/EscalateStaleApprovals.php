<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Console\Command;

class EscalateStaleApprovals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approvals:escalate-stale';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag pending transaction approvals waiting more than 24 hours as escalated';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stale = Transaction::where('status', 'pending')
            ->where('requires_approval', true)
            ->whereNull('escalated_at')
            ->where('created_at', '<', now()->subHours(24))
            ->get();

        foreach ($stale as $transaction) {
            $transaction->update(['escalated_at' => now()]);

            AuditLog::record('escalated', 'transactions',
                "Transaction {$transaction->reference} escalated — pending approval (Level {$transaction->approval_level_reached}/{$transaction->approval_level_required}) for over 24 hours.");
        }

        $this->info("Escalated {$stale->count()} stale approval(s).");

        return self::SUCCESS;
    }
}
