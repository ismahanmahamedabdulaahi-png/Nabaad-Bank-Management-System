<?php

namespace App\Console\Commands;

use App\Services\LoanService;
use Illuminate\Console\Command;

class MarkOverdueLoans extends Command
{
    protected $signature   = 'loans:mark-overdue';
    protected $description = 'Mark overdue loan installments and apply penalties';

    public function handle(LoanService $service): int
    {
        $count = $service->applyPenalties();
        $this->info("Processed {$count} overdue installment(s).");
        return Command::SUCCESS;
    }
}
