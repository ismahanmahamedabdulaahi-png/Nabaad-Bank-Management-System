<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Cheque;
use App\Models\Complaint;
use App\Models\ComplianceCase;
use App\Models\Customer;
use App\Models\KycVerification;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    // Every stat/chart here is gated behind the same permission its own admin
    // page already requires, so a Teller and a Loan Officer see a genuinely
    // different (and cheaper to compute) dashboard rather than everyone
    // getting the Super Admin's full view with parts merely hidden client-side.
    public function index(Request $request): Response
    {
        $user  = Auth::user();
        $today = today();

        $canApprovals    = $user->can('approvals.view');
        $canTransactions = $user->can('transactions.view');
        $canAccounts     = $user->can('accounts.view');
        $canLoans        = $user->can('loans.view');
        $canCheques      = $user->can('cheques.view');

        $range = (int) $request->input('range', 14);
        $range = in_array($range, [7, 14, 30, 90], true) ? $range : 14;

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'customers'          => $user->can('customers.view') ? Customer::where('status', 'active')->count() : null,
                'active_accounts'    => $canAccounts ? Account::where('status', 'active')->count() : null,
                'today_transactions' => $canTransactions ? Transaction::whereDate('created_at', $today)->count() : null,
                'total_deposits'     => $canAccounts ? (float) Account::where('status', 'active')->sum('balance') : null,
                'pending_approvals'  => $canApprovals ? Transaction::where('status', 'pending')->where('requires_approval', true)->count() : null,
                'pending_cheques'    => $canCheques ? Cheque::where('status', 'pending_clearance')->count() : null,
                'active_loans'       => $canLoans ? Loan::whereIn('status', ['active', 'overdue'])->count() : null,
                'today_withdrawals'  => $canTransactions
                    ? (float) Transaction::where('status', 'completed')->where('type', 'withdrawal')->whereDate('created_at', $today)->sum('amount')
                    : null,
            ],
            'pending_approvals' => $canApprovals
                ? Transaction::with(['account.customer', 'processedBy'])
                    ->where('status', 'pending')
                    ->where('requires_approval', true)
                    ->latest()
                    ->limit(5)
                    ->get()
                : [],
            'pending_approvals_count' => $canApprovals
                ? Transaction::where('status', 'pending')->where('requires_approval', true)->count()
                : 0,
            'recent_transactions' => $canTransactions
                ? Transaction::with('account')
                    ->where('status', 'completed')
                    ->latest()
                    ->limit(8)
                    ->get()
                : [],
            'charts' => [
                'transaction_trend' => $canTransactions ? $this->transactionTrend($range) : null,
                'account_mix'       => $canAccounts ? $this->accountMix() : null,
                'loan_status'       => $canLoans ? $this->loanStatusBreakdown() : null,
            ],
            'net_flow'    => $canTransactions ? $this->netFlow($range) : null,
            'trend_range' => $range,
            'alerts'      => $this->systemAlerts($user),
        ]);
    }

    // Daily deposit/withdrawal/transfer totals for the last N days, zero-filled.
    // Transfer legs are stored as two rows per transfer (debit + a "-CR" credit
    // leg, same type/amount) — excluding the "-CR" reference avoids double-
    // counting transfer volume, same convention used in netFlow() below.
    private function transactionTrend(int $days): array
    {
        $from  = today()->subDays($days - 1);
        $today = today();

        $rows = Transaction::selectRaw('DATE(created_at) as day, type, SUM(amount) as total')
            ->where('status', 'completed')
            ->whereIn('type', ['deposit', 'withdrawal', 'transfer'])
            ->where(fn ($q) => $q->where('type', '!=', 'transfer')->orWhere('reference', 'not like', '%-CR'))
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->groupBy('day', 'type')
            ->get()
            ->groupBy('day');

        $labels = [];
        $deposits = [];
        $withdrawals = [];
        $transfers = [];

        for ($date = $from->copy(); $date->lte($today); $date->addDay()) {
            $key = $date->toDateString();
            $labels[] = $date->format('M j');

            $dayRows = $rows->get($key, collect());
            $deposits[]    = (float) $dayRows->firstWhere('type', 'deposit')?->total;
            $withdrawals[] = (float) $dayRows->firstWhere('type', 'withdrawal')?->total;
            $transfers[]   = (float) $dayRows->firstWhere('type', 'transfer')?->total;
        }

        return compact('labels', 'deposits', 'withdrawals', 'transfers');
    }

    // Deposits/withdrawals in full; transfers are internal (money doesn't leave
    // or enter the bank), so they're shown for volume context but excluded
    // from the net-flow figure itself.
    private function netFlow(int $days): array
    {
        $from = today()->subDays($days - 1)->startOfDay();
        $to   = today()->endOfDay();

        $deposits    = (float) Transaction::where('status', 'completed')->where('type', 'deposit')
            ->whereBetween('created_at', [$from, $to])->sum('amount');
        $withdrawals = (float) Transaction::where('status', 'completed')->where('type', 'withdrawal')
            ->whereBetween('created_at', [$from, $to])->sum('amount');
        $transfers   = (float) Transaction::where('status', 'completed')->where('type', 'transfer')
            ->where('reference', 'not like', '%-CR')
            ->whereBetween('created_at', [$from, $to])->sum('amount');

        return [
            'deposits'    => $deposits,
            'withdrawals' => $withdrawals,
            'transfers'   => $transfers,
            'net_flow'    => $deposits - $withdrawals,
        ];
    }

    private function accountMix(): array
    {
        $rows = Account::selectRaw('account_type, COUNT(*) as count')
            ->groupBy('account_type')
            ->pluck('count', 'account_type');

        return [
            'labels' => ['Savings', 'Current', 'Fixed Deposit'],
            'values' => [
                (int) ($rows['savings'] ?? 0),
                (int) ($rows['current'] ?? 0),
                (int) ($rows['fixed_deposit'] ?? 0),
            ],
        ];
    }

    private function loanStatusBreakdown(): array
    {
        $rows = Loan::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        $statuses = ['active', 'overdue', 'closed', 'defaulted', 'pending', 'under_review', 'approved', 'rejected'];
        $labels = [];
        $values = [];

        foreach ($statuses as $status) {
            $count = (int) ($rows[$status] ?? 0);
            if ($count > 0) {
                $labels[] = ucfirst(str_replace('_', ' ', $status));
                $values[] = $count;
            }
        }

        return compact('labels', 'values');
    }

    // Three of the four alert types stakeholders asked for map to real data.
    // A "system maintenance scheduled" alert doesn't — there's no maintenance-
    // scheduling mechanism anywhere in this app, so it's deliberately omitted
    // rather than faked.
    private function systemAlerts($user): array
    {
        $alerts = [];

        if ($user->can('transactions.view')) {
            $rejected = Transaction::where('status', 'rejected')->whereDate('updated_at', today())->count();
            if ($rejected > 0) {
                $alerts[] = [
                    'level' => 'critical',
                    'icon'  => 'bi-x-circle-fill',
                    'label' => $rejected . ' Rejected Transaction' . ($rejected > 1 ? 's' : '') . ' Today',
                    'href'  => route('admin.transactions.index', ['status' => 'rejected']),
                ];
            }
        }

        if ($user->can('approvals.view')) {
            $pending = Transaction::where('status', 'pending')->where('requires_approval', true)->count();
            if ($pending > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-hourglass-split',
                    'label' => $pending . ' Pending Approval' . ($pending > 1 ? 's' : ''),
                    'href'  => route('admin.approvals.index'),
                ];
            }
        }

        if ($user->can('kyc.view')) {
            $kycPending = KycVerification::whereIn('status', ['pending', 'under_review'])->count();
            if ($kycPending > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-person-exclamation',
                    'label' => $kycPending . ' Account' . ($kycPending > 1 ? 's' : '') . ' Awaiting Verification',
                    'href'  => route('admin.kyc.index'),
                ];
            }
        }

        if ($user->can('compliance.view')) {
            $openCases = ComplianceCase::whereIn('status', ['open', 'reviewing'])->count();
            if ($openCases > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-shield-exclamation',
                    'label' => $openCases . ' Open Compliance Case' . ($openCases > 1 ? 's' : ''),
                    'href'  => route('admin.compliance.index'),
                ];
            }
        }

        if ($user->can('complaints.view')) {
            $openComplaints = Complaint::where('status', 'open')->count();
            if ($openComplaints > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-headset',
                    'label' => $openComplaints . ' Unassigned Complaint' . ($openComplaints > 1 ? 's' : ''),
                    'href'  => route('admin.complaints.index', ['status' => 'open']),
                ];
            }
        }

        return $alerts;
    }
}
