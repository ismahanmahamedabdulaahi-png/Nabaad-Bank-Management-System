<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private LoanService $service) {}

    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        $loans = Loan::where('customer_id', $customer->id)
            ->with('account:id,account_number')
            ->latest()
            ->get();

        return Inertia::render('Customer/Loans/Index', [
            'loans' => $loans,
        ]);
    }

    public function show(Loan $loan): Response
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($loan->customer_id === $customer->id, 403);

        $loan->load([
            'account',
            'repaymentSchedules' => fn ($q) => $q->orderBy('installment_number'),
            'payments'           => fn ($q) => $q->reorder()->latest()->take(24),
        ]);

        $nextDue = $loan->repaymentSchedules
            ->whereNotIn('status', ['paid'])
            ->sortBy('installment_number')
            ->first();

        return Inertia::render('Customer/Loans/Show', [
            'loan'         => $loan,
            'next_due'     => $nextDue,
        ]);
    }

    public function create(): Response
    {
        $customer = Auth::guard('customer')->user();

        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereIn('account_type', ['savings', 'current'])
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency']);

        $defaults = $this->service->loanDefaults();

        return Inertia::render('Customer/Loans/Create', [
            'accounts' => $accounts,
            'defaults' => $defaults,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'account_id'           => 'required|exists:accounts,id',
            'amount'               => 'required|numeric|min:100',
            'interest_rate'        => 'required|numeric|min:0|max:50',
            'tenure_months'        => 'required|integer|min:1|max:360',
            'first_repayment_date' => 'required|date|after:today',
            'purpose'              => 'required|string|max:200',
            'collateral'           => 'nullable|string|max:200',
        ]);

        // Verify account belongs to this customer
        $account = Account::findOrFail($data['account_id']);
        abort_unless($account->customer_id === $customer->id, 403, 'Account does not belong to you.');
        abort_unless($account->isOperational(), 422, 'Selected account is not active.');

        $loan = $this->service->apply($customer, $account, $data);

        return redirect()->route('customer.loans.show', $loan)
            ->with('success', "Loan application {$loan->loan_number} submitted. Awaiting bank review.");
    }

    public function repay(Request $request, Loan $loan): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($loan->customer_id === $customer->id, 403);
        abort_unless(in_array($loan->status, ['active', 'overdue']), 422, 'This loan is not eligible for repayment.');

        $data = $request->validate([
            'schedule_id' => 'required|exists:loan_repayment_schedules,id',
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:200',
        ]);

        $installment = LoanRepaymentSchedule::findOrFail($data['schedule_id']);
        abort_unless($installment->loan_id === $loan->id, 403, 'Schedule does not belong to this loan.');

        $this->service->makeRepayment($loan, $installment, (float) $data['amount'], $data['notes'] ?? null);

        return back()->with('success', 'Repayment of USD ' . number_format($data['amount'], 2) . ' posted successfully.');
    }
}
