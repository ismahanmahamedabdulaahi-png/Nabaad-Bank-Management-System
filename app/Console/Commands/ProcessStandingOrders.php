<?php

namespace App\Console\Commands;

use App\Services\StandingOrderService;
use Illuminate\Console\Command;

class ProcessStandingOrders extends Command
{
    protected $signature   = 'standing-orders:execute';
    protected $description = 'Execute all active standing orders that are due today';

    public function handle(StandingOrderService $service): int
    {
        $this->info('Processing due standing orders…');

        $results = $service->executeDue();

        $this->table(['Status', 'Count'], [
            ['Success', $results['success']],
            ['Failed',  $results['failed']],
        ]);

        return Command::SUCCESS;
    }
}
