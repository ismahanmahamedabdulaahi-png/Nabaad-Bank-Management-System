<?php

namespace App\Console\Commands;

use App\Services\ComplianceService;
use Illuminate\Console\Command;

class DetectStructuring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:detect-structuring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag customers with multiple sub-threshold transactions in a 24h window that together look like structuring';

    /**
     * Execute the console command.
     */
    public function handle(ComplianceService $compliance): int
    {
        $opened = $compliance->detectStructuring();

        $this->info("Opened {$opened} structuring case(s).");

        return self::SUCCESS;
    }
}
