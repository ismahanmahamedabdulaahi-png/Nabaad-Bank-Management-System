<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseTillRequest;
use App\Http\Requests\Admin\OpenTillRequest;
use App\Http\Requests\Admin\TillCashMovementRequest;
use App\Models\ReplenishmentRequest;
use App\Models\TellerTill;
use App\Models\User;
use App\Services\TellerService;
use App\Services\VaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TellerController extends Controller
{
    public function __construct(
        private TellerService $service,
        private VaultService  $vault,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TellerTill::class);

        $date    = $request->date ?? today()->toDateString();
        $filters = ['date' => $date];

        $tills = TellerTill::with(['teller', 'assignedBy', 'openedBy', 'closedBy'])
            ->where('business_date', $date)
            ->orderBy('status')
            ->orderBy('opened_at')
            ->get();

        $pendingRequests = ReplenishmentRequest::with(['till.teller', 'requestedBy'])
            ->whereHas('till', fn ($q) => $q->where('business_date', $date))
            ->where('status', 'pending')
            ->latest()
            ->get();

        $stats = [
            'open'             => $tills->where('status', 'open')->count(),
            'closed'           => $tills->where('status', 'closed')->count(),
            'total_opening'    => $tills->sum('opening_balance'),
            'total_current'    => $tills->where('status', 'open')->sum('current_balance'),
            'total_txns_today' => \App\Models\Transaction::whereDate('created_at', $date)
                                  ->where('status', 'completed')->count(),
            'pending_requests' => $pendingRequests->count(),
        ];

        $performance = \App\Models\Transaction::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->whereIn('teller_till_id', $tills->pluck('id'))
            ->selectRaw('teller_till_id, type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('teller_till_id', 'type')
            ->get()
            ->groupBy('teller_till_id');

        return Inertia::render('Admin/Tellers/Index', [
            'tills'           => $tills,
            'filters'         => $filters,
            'stats'           => $stats,
            'performance'     => $performance,
            'pending_requests'=> $pendingRequests,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', TellerTill::class);

        $openTillersToday = TellerTill::where('business_date', today()->toDateString())
            ->where('status', 'open')
            ->pluck('teller_id');

        $tellers = User::whereHas('roles', fn ($q) => $q->where('name', 'Teller'))
            ->where('status', 'active')
            ->whereNotIn('id', $openTillersToday)
            ->get(['id', 'name', 'email']);

        $vault = $this->vault->getVault();

        return Inertia::render('Admin/Tellers/Assign', [
            'tellers'        => $tellers,
            'business_date'  => today()->toDateString(),
            'vault_balance'  => (float) $vault->balance,
            'vault_open'     => $vault->isOpen(),
        ]);
    }

    public function store(OpenTillRequest $request): RedirectResponse
    {
        $this->authorize('create', TellerTill::class);

        $data   = $request->validated();
        $teller = User::findOrFail($data['teller_id']);
        $till   = $this->service->openTill($teller, $data);

        // Debit vault for opening cash (vault records the transfer; till balance set separately)
        $opening = (float) ($data['opening_balance'] ?? 0);
        if ($opening > 0) {
            $this->vault->recordTillOpening($till, $opening, null);
            // Set the till's current_balance and opening_balance to the funded amount
            $till->update(['opening_balance' => $opening, 'current_balance' => $opening]);
        }

        return redirect()->route('admin.tellers.show', $till)
            ->with('success', "Till opened for {$teller->name} with USD " . number_format($opening, 2) . ' from vault.');
    }

    public function show(TellerTill $teller): Response
    {
        $this->authorize('view', $teller);

        $teller->load(['teller', 'assignedBy', 'openedBy', 'closedBy', 'cashMovements.processedBy', 'cashMovements.relatedTill.teller']);

        $transactions = \App\Models\Transaction::where('teller_till_id', $teller->id)
            ->with(['account.customer', 'processedBy'])
            ->latest()
            ->get()
            ->map(fn ($t) => array_merge($t->toArray(), [
                'direction' => $t->balance_after >= $t->balance_before ? 'credit' : 'debit',
            ]));

        $openTills = TellerTill::where('status', 'open')
            ->where('id', '!=', $teller->id)
            ->with('teller')
            ->get(['id', 'till_name', 'teller_id', 'current_balance']);

        // Replenishment requests for this till
        $replenishmentRequests = ReplenishmentRequest::with(['requestedBy', 'reviewedBy'])
            ->where('till_id', $teller->id)
            ->latest()
            ->get();

        return Inertia::render('Admin/Tellers/Show', [
            'till'                  => array_merge($teller->toArray(), [
                'expected_balance' => $teller->expectedBalance(),
            ]),
            'transactions'          => $transactions,
            'open_tills'            => $openTills,
            'replenishment_requests'=> $replenishmentRequests,
        ]);
    }

    public function close(CloseTillRequest $request, TellerTill $teller): RedirectResponse
    {
        $this->authorize('close', $teller);

        $till = $this->service->closeTill($teller, $request->validated());

        return redirect()->route('admin.tellers.show', $till)
            ->with('success', "Till closed. Variance: USD {$till->variance}.");
    }

    public function replenish(TillCashMovementRequest $request, TellerTill $teller): RedirectResponse
    {
        $this->authorize('transfer', TellerTill::class);

        $this->vault->transferToTeller($teller, (float) $request->amount, $request->notes);

        return back()->with('success', 'USD ' . number_format($request->amount, 2) . " transferred from vault to {$teller->till_name}.");
    }

    public function returnCash(TillCashMovementRequest $request, TellerTill $teller): RedirectResponse
    {
        $this->authorize('transfer', TellerTill::class);

        $this->vault->receiveFromTeller($teller, (float) $request->amount, $request->notes);

        return back()->with('success', 'USD ' . number_format($request->amount, 2) . " returned from {$teller->till_name} to vault.");
    }

    public function transfer(TillCashMovementRequest $request, TellerTill $teller): RedirectResponse
    {
        $this->authorize('transfer', TellerTill::class);

        $toTill = TellerTill::findOrFail($request->to_till_id);
        $this->service->transferBetweenTills($teller, $toTill, (float) $request->amount, $request->notes);

        return back()->with('success', "USD {$request->amount} transferred to {$toTill->till_name}.");
    }

    // ── Replenishment Request / Approval ──────────────────────────────────────

    public function requestReplenishment(Request $request, TellerTill $teller): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->requestReplenishment($teller, (float) $request->amount, $request->reason);

        return back()->with('success', 'Replenishment request submitted. Awaiting supervisor approval.');
    }

    public function approveReplenishment(Request $request, TellerTill $teller): RedirectResponse
    {
        $this->authorize('transfer', TellerTill::class);

        $request->validate(['request_id' => ['required', 'exists:replenishment_requests,id']]);

        $req = ReplenishmentRequest::findOrFail($request->request_id);

        $this->service->approveReplenishment($req, $request->notes);

        // Execute the actual vault → till transfer
        $this->vault->transferToTeller($teller, (float) $req->amount, "Approved replenishment request #{$req->id}");

        return back()->with('success', 'Replenishment approved. USD ' . number_format($req->amount, 2) . " transferred from vault to {$teller->till_name}.");
    }

    public function rejectReplenishment(Request $request, TellerTill $teller): RedirectResponse
    {
        $this->authorize('transfer', TellerTill::class);

        $request->validate([
            'request_id' => ['required', 'exists:replenishment_requests,id'],
            'reason'     => ['required', 'string', 'min:5'],
        ]);

        $req = ReplenishmentRequest::findOrFail($request->request_id);

        $this->service->rejectReplenishment($req, $request->reason);

        return back()->with('success', 'Replenishment request rejected.');
    }
}
