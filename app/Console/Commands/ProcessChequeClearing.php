<?php

namespace App\Console\Commands;

use App\Services\ChequeService;
use Illuminate\Console\Command;

class ProcessChequeClearing extends Command
{
    protected $signature   = 'cheques:process-clearing';
    protected $description = 'Clear pending cheques whose clearing date has arrived';

    public function handle(ChequeService $service): int
    {
        $count = $service->processClearing();
        $this->info("Cleared {$count} cheque(s).");
        return Command::SUCCESS;
    }
}
