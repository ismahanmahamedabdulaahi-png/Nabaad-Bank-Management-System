<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\StandingOrder;
use App\Services\StandingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StandingOrderController extends Controller
{
    public function __construct(private StandingOrderService $service) {}

    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        $orders = StandingOrder::whereHas('sourceAccount', fn ($q) =>
                $q->where('customer_id', $customer->id)
            )
            ->with(['sourceAccount', 'beneficiaryAccount.customer'])
            ->latest()
            ->get();

        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency']);

        return Inertia::render('Customer/StandingOrders/Index', [
            'orders'   => $orders,
            'accounts' => $accounts,
        ]);
    }

    public function create(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency']);

        // All active accounts (for beneficiary search)
        $allAccounts = Account::where('status', 'active')
            ->with('customer:id,name')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency', 'customer_id']);

        // Deep-linked from an account's own "Transfer" button (Accounts/Show.vue)
        $preselectedId = $accounts->firstWhere('id', $request->query('source_account_id'))?->id;

        return Inertia::render('Customer/StandingOrders/Create', [
            'my_accounts' => $accounts,
            'all_accounts'=> $allAccounts,
            'preselected_source_account_id' => $preselectedId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'source_account_id'      => 'required|exists:accounts,id',
            'beneficiary_account_id' => 'required|exists:accounts,id|different:source_account_id',
            'amount'                 => 'required|numeric|min:1',
            'frequency'              => 'required|in:weekly,monthly',
            'start_date'             => 'required|date|after_or_equal:today',
            'end_date'               => 'nullable|date|after:start_date',
            'description'            => 'nullable|string|max:200',
        ]);

        // Verify source account belongs to this customer
        $source = Account::findOrFail($data['source_account_id']);
        abort_unless($source->customer_id === $customer->id, 403, 'Account does not belong to you.');

        // Inject customer as the creator
        $order = StandingOrder::create([
            'source_account_id'      => $data['source_account_id'],
            'beneficiary_account_id' => $data['beneficiary_account_id'],
            'amount'                 => $data['amount'],
            'frequency'              => $data['frequency'],
            'start_date'             => $data['start_date'],
            'next_execution_date'    => $data['start_date'],
            'end_date'               => $data['end_date'] ?? null,
            'description'            => $data['description'] ?? null,
            'status'                 => 'active',
            'created_by_customer'    => $customer->id,
        ]);

        return redirect()->route('customer.standing-orders.index')
            ->with('success', "Standing order created. First transfer on {$order->start_date->format('d M Y')}.");
    }

    public function pause(StandingOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);
        $this->service->pause($order);
        return back()->with('success', 'Standing order paused.');
    }

    public function resume(StandingOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);
        $this->service->resume($order);
        return back()->with('success', 'Standing order resumed.');
    }

    public function cancel(StandingOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);
        $this->service->cancel($order);
        return back()->with('success', 'Standing order cancelled.');
    }

    private function authorizeOrder(StandingOrder $order): void
    {
        $customer = Auth::guard('customer')->user();
        $order->load('sourceAccount');
        abort_unless($order->sourceAccount?->customer_id === $customer->id, 403);
    }
}
