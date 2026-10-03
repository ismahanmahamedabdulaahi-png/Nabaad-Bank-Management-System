<?php

namespace App\Console\Commands;

use App\Models\KycDocument;
use App\Models\User;
use App\Notifications\KycDocumentExpiring;
use Illuminate\Console\Command;

class CheckAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan for KYC documents nearing expiry and notify staff who can review KYC';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiring = KycDocument::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->with('kycVerification.customer')
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('No KYC documents expiring within 14 days.');
            return self::SUCCESS;
        }

        $reviewers = User::permission('kyc.view')->where('status', 'active')->get();
        $sent = 0;

        foreach ($expiring as $document) {
            // Dedupe: skip if we already notified about this document in the last 7 days
            $alreadyNotified = $reviewers->first()
                ?->notifications()
                ->where('type', KycDocumentExpiring::class)
                ->where('data->document_id', $document->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if ($alreadyNotified) continue;

            foreach ($reviewers as $reviewer) {
                $reviewer->notify(new KycDocumentExpiring($document));
            }
            $sent++;
        }

        $this->info("Notified reviewers about {$sent} expiring KYC document(s).");

        return self::SUCCESS;
    }
}
