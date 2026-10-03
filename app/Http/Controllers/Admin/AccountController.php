<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenAccountRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private AccountService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $filters = $request->only(['search', 'account_type', 'status', 'branch_id', 'sort', 'direction']);

        $stats = [
            'total'         => Account::count(),
            'active'        => Account::where('status', 'active')->count(),
            'frozen'        => Account::where('status', 'frozen')->count(),
            'fixed_deposit' => Account::where('account_type', 'fixed_deposit')->count(),
            'maturity_due'  => Account::where('account_type', 'fixed_deposit')
                                      ->where('status', '!=', 'closed')
                                      ->where('status', '!=', 'matured')
                                      ->where('fd_maturity_date', '<=', now()->toDateString())
                                      ->count(),
        ];

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $this->service->list($filters),
            'branches' => Branch::where('status', 'active')->get(['id', 'name', 'code']),
            'filters'  => $filters,
            'stats'    => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Account::class);

        $preselectedCustomer = null;
        if ($request->has('customer_id')) {
            $preselectedCustomer = Customer::where('id', $request->customer_id)
                ->where('status', 'active')
                ->whereHas('kyc', fn ($q) => $q->where('status', 'approved'))
                ->select('id', 'name', 'customer_number', 'email', 'phone')
                ->first();
        }

        return Inertia::render('Admin/Accounts/Create', [
            'branches'  => Branch::where('status', 'active')->get(['id', 'name', 'code']),
            'customers' => Customer::where('status', 'active')
                ->whereHas('kyc', fn ($q) => $q->where('status', 'approved'))
                ->select('id', 'name', 'customer_number', 'email', 'phone')
                ->orderBy('name')
                ->get(),
            'preselectedCustomer' => $preselectedCustomer,
        ]);
    }

    public function store(OpenAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $customer = Customer::findOrFail($request->validated()['customer_id']);
        $account  = $this->service->open($customer, $request->validated());

        return redirect()->route('admin.accounts.show', $account)
            ->with('success', "Account {$account->account_number} opened successfully.");
    }

    public function show(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        $account->load(['customer', 'branch', 'openedBy']);

        $filters = $request->only(['date_from', 'date_to', 'type']);

        $recentTransactions = $account->transactions()
            ->with('processedBy')
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($t) => array_merge($t->toArray(), [
                'direction' => $t->balance_after >= $t->balance_before ? 'credit' : 'debit',
            ]));

        return Inertia::render('Admin/Accounts/Show', [
            'account'            => $account,
            'recentTransactions' => $recentTransactions,
            'filters'            => $filters,
        ]);
    }

    public function freeze(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('freeze', $account);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->service->freeze($account, $request->reason);

        AuditLog::record('account_frozen', 'accounts',
            "Account {$account->account_number} frozen. Reason: " . ($request->reason ?: 'not specified'));

        return back()->with('success', "Account {$account->account_number} has been frozen.");
    }

    public function unfreeze(Account $account): RedirectResponse
    {
        $this->authorize('unfreeze', $account);

        $this->service->unfreeze($account);

        AuditLog::record('account_unfrozen', 'accounts',
            "Account {$account->account_number} unfrozen.");

        return back()->with('success', "Account {$account->account_number} has been unfrozen.");
    }

    public function close(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('close', $account);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->service->close($account, $request->reason);

        AuditLog::record('account_closed', 'accounts',
            "Account {$account->account_number} closed. Reason: " . ($request->reason ?: 'not specified'));

        return back()->with('success', "Account {$account->account_number} has been closed.");
    }

    public function reactivate(Account $account): RedirectResponse
    {
        $this->authorize('reactivate', $account);

        $this->service->reactivate($account);

        AuditLog::record('account_reactivated', 'accounts',
            "Dormant account {$account->account_number} reactivated.");

        return back()->with('success', "Account {$account->account_number} has been reactivated.");
    }
}
