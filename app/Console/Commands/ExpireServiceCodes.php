<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ServiceRequestCode;
use Illuminate\Console\Command;

class ExpireServiceCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-codes:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire cardless withdrawal/deposit codes past their expiry, rejecting the underlying pending withdrawal if one exists';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = ServiceRequestCode::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->with('transaction')
            ->get();

        foreach ($expired as $code) {
            $code->update(['status' => 'expired']);

            // Deposit codes never had a transaction row (no money existed
            // yet); a withdrawal code's pending transaction never touched the
            // account balance either, so "releasing" it is just marking it
            // rejected — there is nothing to reverse.
            if ($code->type === 'withdrawal' && $code->transaction?->status === 'pending') {
                $code->transaction->update(['status' => 'rejected']);
            }

            AuditLog::record('service_code.expired', 'service_request_codes',
                "{$code->type} code {$code->code} expired unused.");
        }

        $this->info("Expired {$expired->count()} service code(s).");

        return self::SUCCESS;
    }
}
