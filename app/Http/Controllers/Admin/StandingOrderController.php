<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateStandingOrderRequest;
use App\Models\Account;
use App\Models\StandingOrder;
use App\Services\StandingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StandingOrderController extends Controller
{
    public function __construct(private StandingOrderService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StandingOrder::class);

        $filters = $request->only(['search', 'status', 'frequency']);

        $query = StandingOrder::with(['sourceAccount.customer', 'beneficiaryAccount.customer', 'createdByUser']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->whereHas('sourceAccount', fn ($a) =>
                $a->where('account_number', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"))
            );
        }

        if (!empty($filters['status']))    $query->where('status',    $filters['status']);
        if (!empty($filters['frequency'])) $query->where('frequency', $filters['frequency']);

        $orders = $query->orderBy('next_execution_date')->paginate(15)->withQueryString();

        $stats = [
            'active'   => StandingOrder::where('status', 'active')->count(),
            'due_today'=> StandingOrder::where('status', 'active')->where('next_execution_date', '<=', today())->count(),
            'paused'   => StandingOrder::where('status', 'paused')->count(),
        ];

        return Inertia::render('Admin/StandingOrders/Index', [
            'orders'  => $orders,
            'filters' => $filters,
            'stats'   => $stats,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StandingOrder::class);

        return Inertia::render('Admin/StandingOrders/Create', [
            'accounts' => Account::with('customer')
                ->where('status', 'active')
                ->select('id', 'account_number', 'customer_id', 'account_type', 'balance', 'currency')
                ->orderBy('account_number')
                ->get(),
        ]);
    }

    public function store(CreateStandingOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', StandingOrder::class);

        $order = $this->service->create($request->validated());

        return redirect()->route('admin.standing-orders.index')
            ->with('success', "Standing order created. First execution: {$order->next_execution_date->format('d M Y')}.");
    }

    public function show(StandingOrder $standingOrder): Response
    {
        $this->authorize('view', $standingOrder);

        $standingOrder->load(['sourceAccount.customer', 'beneficiaryAccount.customer', 'createdByUser']);

        return Inertia::render('Admin/StandingOrders/Show', [
            'order' => $standingOrder,
        ]);
    }

    public function pause(StandingOrder $standingOrder): RedirectResponse
    {
        $this->authorize('manage', $standingOrder);
        $this->service->pause($standingOrder);
        return back()->with('success', 'Standing order paused.');
    }

    public function resume(StandingOrder $standingOrder): RedirectResponse
    {
        $this->authorize('manage', $standingOrder);
        $this->service->resume($standingOrder);
        return back()->with('success', 'Standing order resumed.');
    }

    public function cancel(StandingOrder $standingOrder): RedirectResponse
    {
        $this->authorize('manage', $standingOrder);
        $this->service->cancel($standingOrder);
        return back()->with('success', 'Standing order cancelled.');
    }
}
