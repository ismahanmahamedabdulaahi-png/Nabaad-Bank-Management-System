<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanActionRequest;
use App\Http\Requests\Admin\LoanApplicationRequest;
use App\Http\Requests\Admin\LoanRepaymentRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private LoanService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Loan::class);

        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        $query = Loan::with(['customer', 'account'])
            ->when($filters['search'] ?? null, fn ($q, $s) =>
                $q->where('loan_number', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"))
            )
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'       => Loan::count(),
            'active'      => Loan::where('status', 'active')->count(),
            'overdue'     => Loan::where('status', 'overdue')->count(),
            'pending'     => Loan::whereIn('status', ['pending', 'under_review', 'approved'])->count(),
            'outstanding' => Loan::whereIn('status', ['active', 'overdue'])->sum('outstanding_balance'),
            'disbursed'   => Loan::whereIn('status', ['active', 'overdue', 'closed'])->sum('amount'),
        ];

        return Inertia::render('Admin/Loans/Index', [
            'loans'   => $query,
            'filters' => $filters,
            'stats'   => $stats,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Loan::class);

        $customers = Customer::where('status', 'active')
            ->whereHas('accounts', fn ($q) => $q->where('status', 'active'))
            ->get(['id', 'name', 'customer_number']);

        return Inertia::render('Admin/Loans/Create', [
            'customers' => $customers,
            'defaults'  => $this->service->loanDefaults(),
        ]);
    }

    public function eligibility(Customer $customer)
    {
        $this->authorize('create', Loan::class);

        $issues = [];

        if ($customer->status !== 'active') {
            $issues[] = 'Customer account is not active (status: ' . $customer->status . ').';
        }

        $activeLoans = Loan::where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'overdue', 'pending', 'under_review', 'approved'])
            ->count();
        if ($activeLoans > 0) {
            $issues[] = "Customer has {$activeLoans} active/pending loan(s). Settlement required before a new loan.";
        }

        $overdueLoans = Loan::where('customer_id', $customer->id)
            ->where('status', 'overdue')
            ->count();
        if ($overdueLoans > 0) {
            $issues[] = "Customer has {$overdueLoans} overdue loan(s) — high risk.";
        }

        $hasActiveAccount = $customer->accounts()->where('status', 'active')->exists();
        if (!$hasActiveAccount) {
            $issues[] = 'Customer has no active accounts for disbursement.';
        }

        return response()->json([
            'eligible' => empty($issues),
            'issues'   => $issues,
        ]);
    }

    public function store(LoanApplicationRequest $request): RedirectResponse
    {
        $this->authorize('create', Loan::class);

        $customer = Customer::findOrFail($request->customer_id);
        $account  = Account::findOrFail($request->account_id);

        $loan = $this->service->apply($customer, $account, $request->validated());

        return redirect()->route('admin.loans.show', $loan)
            ->with('success', "Loan application {$loan->loan_number} submitted successfully.");
    }

    public function show(Loan $loan): Response
    {
        $this->authorize('view', $loan);

        $loan->load([
            'customer', 'account', 'reviewedBy', 'approvedBy', 'disbursedBy',
            'repaymentSchedules.payments.processedBy',
            'payments' => fn ($q) => $q->latest()->with(['schedule', 'processedBy']),
        ]);

        $nextInstallment = $loan->repaymentSchedules
            ->whereNotIn('status', ['paid'])
            ->sortBy('installment_number')
            ->first();

        return Inertia::render('Admin/Loans/Show', [
            'loan'             => $loan,
            'next_installment' => $nextInstallment,
        ]);
    }

    public function review(LoanActionRequest $request, Loan $loan): RedirectResponse
    {
        $this->authorize('review', $loan);

        $this->service->review($loan, $request->notes ?? '');

        return back()->with('success', "Loan {$loan->loan_number} sent for review.");
    }

    public function approve(LoanActionRequest $request, Loan $loan): RedirectResponse
    {
        $this->authorize('approve', $loan);

        $this->service->approve($loan, $request->notes);
        AuditLog::record('approved', 'loans', "Loan {$loan->loan_number} approved for {$loan->customer?->name}");

        return back()->with('success', "Loan {$loan->loan_number} approved.");
    }

    public function reject(LoanActionRequest $request, Loan $loan): RedirectResponse
    {
        $this->authorize('approve', $loan);

        $reason = $request->reason ?? $request->notes ?? 'No reason provided.';
        $this->service->reject($loan, $reason);
        AuditLog::record('rejected', 'loans', "Loan {$loan->loan_number} rejected: {$reason}");

        return back()->with('success', "Loan {$loan->loan_number} rejected.");
    }

    public function disburse(LoanActionRequest $request, Loan $loan): RedirectResponse
    {
        $this->authorize('disburse', $loan);

        $this->service->disburse($loan);
        AuditLog::record('created', 'loans', "Loan {$loan->loan_number} disbursed USD {$loan->amount} → {$loan->account->account_number}");

        return back()->with('success', "Loan {$loan->loan_number} disbursed to account {$loan->account->account_number}.");
    }

    public function repay(LoanRepaymentRequest $request, Loan $loan): RedirectResponse
    {
        $this->authorize('repay', $loan);

        $installment = LoanRepaymentSchedule::findOrFail($request->schedule_id);

        if ($installment->loan_id !== $loan->id) {
            abort(403, 'The selected schedule does not belong to this loan.');
        }

        $this->service->makeRepayment($loan, $installment, (float) $request->amount, $request->notes);

        return back()->with('success', 'Repayment of USD ' . number_format($request->amount, 2) . ' posted to ' . $loan->loan_number . '.');
    }

    public function accounts(Customer $customer): \Illuminate\Http\JsonResponse
    {
        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency']);

        return response()->json($accounts);
    }
}
