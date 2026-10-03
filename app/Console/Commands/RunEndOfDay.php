<?php

namespace App\Console\Commands;

use App\Services\EodService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunEndOfDay extends Command
{
    protected $signature   = 'eod:run {--date= : Date to run EOD for (Y-m-d), defaults to today} {--force : Re-run even if already completed today}';
    protected $description = 'Run end-of-day processing: standing orders, cheque clearing, loan penalties';

    public function handle(EodService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : today();

        $this->info("Running EOD for {$date->toDateString()}…");

        if ($this->option('force')) {
            // Remove existing completed run to allow re-run
            \App\Models\EodRun::where('run_date', $date->toDateString())
                ->where('status', 'completed')
                ->delete();
        }

        $run = $service->run($date, isManual: true);

        $this->table(
            ['Step', 'Result'],
            [
                ['Standing Orders (success)',  $run->standing_orders_success],
                ['Standing Orders (failed)',   $run->standing_orders_failed],
                ['Standing Orders (skipped)',  $run->standing_orders_skipped],
                ['Cheques Cleared',            $run->cheques_cleared],
                ['Loans Marked Overdue',       $run->loans_marked_overdue],
            ]
        );

        if ($run->isFailed() && $run->errors) {
            $this->error('Some steps failed:');
            foreach ($run->errors as $err) {
                $this->line("  [{$err['step']}] {$err['message']}");
            }
            return Command::FAILURE;
        }

        $secs = $run->durationSeconds();
        $this->info("EOD completed in {$secs}s. Status: {$run->status}");

        return Command::SUCCESS;
    }
}
