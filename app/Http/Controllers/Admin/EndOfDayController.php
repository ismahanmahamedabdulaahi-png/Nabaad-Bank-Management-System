<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EodRun;
use App\Services\BusinessCalendarService;
use App\Services\EodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EndOfDayController extends Controller
{
    public function __construct(
        private EodService              $eod,
        private BusinessCalendarService $calendar,
    ) {}

    public function index(): Response
    {
        $runs = EodRun::with('triggeredBy')
            ->latest('run_date')
            ->paginate(20);

        $todayRun    = EodRun::where('run_date', today())->latest()->first();
        $latestRun   = EodRun::latest('run_date')->first();
        $isWorkingDay= $this->calendar->isWorkingDay(today());

        return Inertia::render('Admin/EndOfDay/Index', [
            'runs'         => $runs,
            'today_run'    => $todayRun,
            'latest_run'   => $latestRun,
            'is_working_day'=> $isWorkingDay,
            'stats'        => [
                'total_runs'      => EodRun::count(),
                'completed_runs'  => EodRun::where('status', 'completed')->count(),
                'failed_runs'     => EodRun::where('status', 'failed')->count(),
                'ran_today'       => (bool) $todayRun?->isCompleted(),
            ],
        ]);
    }

    public function show(EodRun $run): Response
    {
        $run->load('triggeredBy');

        return Inertia::render('Admin/EndOfDay/Show', [
            'run' => $run,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermissionTo('eod.run'), 403);

        $request->validate([
            'force' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($request->boolean('force')) {
            // Mark the previous completed run as superseded — never delete audit records
            EodRun::where('run_date', today())
                ->where('status', 'completed')
                ->update(['status' => 'superseded']);
        }

        $run = $this->eod->run(today(), isManual: true);

        if ($run->notes === null && $request->notes) {
            $run->update(['notes' => $request->notes]);
        }

        $msg = $run->isCompleted()
            ? "EOD completed: {$run->standing_orders_success} standing orders, {$run->cheques_cleared} cheques cleared, {$run->loans_marked_overdue} loans updated."
            : "EOD completed with errors. Check the run log for details.";

        return redirect()->route('admin.eod.index')->with(
            $run->isFailed() ? 'error' : 'success',
            $msg
        );
    }
}
