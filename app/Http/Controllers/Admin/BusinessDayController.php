<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessDay;
use App\Models\TellerTill;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\VaultTransaction;
use App\Services\BusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessDayController extends Controller
{
    public function __construct(private BusinessDayService $service) {}

    public function index(): Response
    {
        $today    = BusinessDay::today();
        $vault    = Vault::first();
        $tills    = TellerTill::with(['teller', 'closedBy'])
                    ->where('business_date', today())
                    ->orderBy('opened_at')
                    ->get();

        // Pending replenishment requests for today
        $pendingRequests = \App\Models\ReplenishmentRequest::with(['till.teller', 'requestedBy'])
            ->whereHas('till', fn ($q) => $q->where('business_date', today()))
            ->where('status', 'pending')
            ->latest()
            ->get();

        $txnCount = Transaction::whereDate('created_at', today())->where('status', 'completed')->count();

        $vaultTxns = VaultTransaction::whereDate('created_at', today())->get();

        // Workflow step completion checks
        $workflow = [
            ['step' => 1, 'action' => 'Open Business Day',       'done' => $today?->isOpen() ?? false,                'by' => 'Branch Manager'],
            ['step' => 2, 'action' => 'Open Vault',              'done' => $vault?->isOpen() ?? false,                'by' => 'Branch Manager'],
            ['step' => 3, 'action' => 'Open Teller Tills',       'done' => $tills->where('status', 'open')->count() > 0, 'by' => 'Teller Supervisor'],
            ['step' => 4, 'action' => 'Assign Opening Cash',     'done' => $vaultTxns->where('type', 'transfer_to_teller')->count() > 0, 'by' => 'Teller Supervisor'],
            ['step' => 5, 'action' => 'Customer Transactions',   'done' => $txnCount > 0,                             'by' => 'Teller'],
            ['step' => 6, 'action' => 'Till Replenishments',     'done' => $vaultTxns->where('type', 'transfer_to_teller')->count() > 1, 'by' => 'Teller Supervisor'],
            ['step' => 7, 'action' => 'Cash Return to Vault',    'done' => $vaultTxns->where('type', 'returned_from_teller')->count() > 0, 'by' => 'Teller Supervisor'],
            ['step' => 8, 'action' => 'Till Reconciliation',     'done' => $tills->where('status', 'closed')->count() === $tills->count() && $tills->count() > 0, 'by' => 'Teller Supervisor'],
            ['step' => 9, 'action' => 'Vault Reconciliation',    'done' => $vault?->isClosed() ?? false,              'by' => 'Branch Manager'],
            ['step' => 10,'action' => 'Close Business Day',      'done' => $today?->isClosed() ?? false,              'by' => 'Branch Manager'],
        ];

        $stats = [
            'open_tills'      => $tills->where('status', 'open')->count(),
            'closed_tills'    => $tills->where('status', 'closed')->count(),
            'txn_count'       => $txnCount,
            'vault_balance'   => $vault?->balance ?? 0,
            'pending_requests'=> $pendingRequests->count(),
        ];

        return Inertia::render('Admin/BusinessDay/Index', [
            'today'           => $today,
            'vault'           => $vault,
            'tills'           => $tills,
            'pending_requests'=> $pendingRequests,
            'workflow'        => $workflow,
            'stats'           => $stats,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $this->authorize('business-day.manage');

        $this->service->open($request->notes);

        return back()->with('success', 'Business day opened for ' . today()->format('l, d M Y') . '.');
    }

    public function close(Request $request): RedirectResponse
    {
        $this->authorize('business-day.manage');

        $this->service->close($request->notes);

        return back()->with('success', 'Business day closed for ' . today()->format('l, d M Y') . '.');
    }
}
