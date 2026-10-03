<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VaultCashMovementRequest;
use App\Models\Vault;
use App\Models\VaultTransaction;
use App\Services\VaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VaultController extends Controller
{
    public function __construct(private VaultService $service) {}

    public function show(Request $request): Response
    {
        $vault = $this->service->getVault();
        $this->authorize('view', $vault);

        $movements = VaultTransaction::with(['processedBy', 'tellerTill.teller'])
            ->when($request->date, fn ($q) => $q->whereDate('created_at', $request->date))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $today = VaultTransaction::whereDate('created_at', today())->get();

        $stats = [
            'cash_in_today'          => $today->where('type', 'cash_in')->sum('amount'),
            'cash_out_today'         => $today->where('type', 'cash_out')->sum('amount'),
            'teller_transfers_today' => $today->where('type', 'transfer_to_teller')->sum('amount'),
            'teller_returns_today'   => $today->where('type', 'returned_from_teller')->sum('amount'),
            'txn_count_today'        => $today->count(),
        ];

        return Inertia::render('Admin/Vault/Show', [
            'vault'     => $vault->load(['branch', 'lastUpdatedBy', 'openedBy', 'closedBy']),
            'movements' => $movements,
            'stats'     => $stats,
            'filters'   => $request->only('date', 'type'),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $vault = $this->service->getVault();
        $this->authorize('cashIn', $vault);

        $this->service->open($request->notes);

        return back()->with('success', 'Vault opened successfully.');
    }

    public function close(Request $request): RedirectResponse
    {
        $vault = $this->service->getVault();
        $this->authorize('cashOut', $vault);

        $this->service->close($request->notes);

        return back()->with('success', 'Vault closed. End-of-day reconciliation complete.');
    }

    public function cashIn(VaultCashMovementRequest $request): RedirectResponse
    {
        $vault = $this->service->getVault();
        $this->authorize('cashIn', $vault);

        $this->service->cashIn((float) $request->amount, $request->notes);

        return back()->with('success', 'USD ' . number_format($request->amount, 2) . ' deposited into vault.');
    }

    public function cashOut(VaultCashMovementRequest $request): RedirectResponse
    {
        $vault = $this->service->getVault();
        $this->authorize('cashOut', $vault);

        $this->service->cashOut((float) $request->amount, $request->notes);

        return back()->with('success', 'USD ' . number_format($request->amount, 2) . ' withdrawn from vault.');
    }
}
