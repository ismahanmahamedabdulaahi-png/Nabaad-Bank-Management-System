<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();
        $customer->load('activeAccounts');

        $accountIds = $customer->activeAccounts->pluck('id');

        // Completed + pending/rejected — a customer should see their own
        // in-flight activity, not just what's already settled.
        $recentTransactions = Transaction::with('account')
            ->whereIn('account_id', $accountIds)
            ->whereIn('status', ['completed', 'pending', 'rejected'])
            ->latest()
            ->take(5)
            ->get();

        $totalBalance = $customer->activeAccounts->sum('balance');

        $stats = [
            'total_balance'   => $totalBalance,
            'account_count'   => $customer->activeAccounts->count(),
            'pending_cheques' => 0,
        ];

        return Inertia::render('Customer/Dashboard', [
            'customer'             => $customer,
            'accounts'             => $customer->activeAccounts,
            'recent_transactions'  => $recentTransactions,
            'stats'                => $stats,
            'loan_summary'         => $this->loanSummary($customer),
            'low_balance_accounts' => $this->lowBalanceAccounts($customer),
        ]);
    }

    // Everything the dashboard's Active Loans card needs in one shape, or null
    // if the customer has no active/overdue loan (renders the empty state).
    private function loanSummary($customer): ?array
    {
        $activeLoanIds = $customer->loans()
            ->whereIn('status', ['active', 'overdue'])
            ->pluck('id');

        if ($activeLoanIds->isEmpty()) {
            return null;
        }

        $nextDue = LoanRepaymentSchedule::whereIn('loan_id', $activeLoanIds)
            ->whereIn('status', ['pending', 'overdue'])
            ->orderBy('due_date')
            ->first();

        return [
            'count'                 => $activeLoanIds->count(),
            'outstanding_balance'   => (float) Loan::whereIn('id', $activeLoanIds)->sum('outstanding_balance'),
            'next_payment_amount'   => $nextDue ? (float) $nextDue->emi_amount : null,
            'next_payment_due_date' => $nextDue?->due_date,
            'next_payment_overdue'  => $nextDue?->status === 'overdue',
            'loan_id'               => $nextDue?->loan_id ?? $activeLoanIds->first(),
        ];
    }

    // Accounts where balance is at or below minimum_balance.
    private function lowBalanceAccounts($customer)
    {
        return $customer->activeAccounts->filter(function ($acc) {
            $minBalance = (float) ($acc->minimum_balance ?? 0);
            $threshold  = max($minBalance, 50); // warn if at/below $50 or minimum_balance
            return (float) $acc->balance <= $threshold;
        })->values();
    }
}
