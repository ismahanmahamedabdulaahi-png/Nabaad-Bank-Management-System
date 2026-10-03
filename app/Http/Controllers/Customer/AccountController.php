<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        $accounts = Account::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Customer/Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function show(Account $account): Response
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($account->customer_id === $customer->id, 403);

        $transactions = Transaction::where('account_id', $account->id)
            ->where('status', 'completed')
            ->with('processedBy')
            ->latest()
            ->paginate(20);

        return Inertia::render('Customer/Accounts/Show', [
            'account'      => $account,
            'transactions' => $transactions,
        ]);
    }

    public function updateAlertThreshold(Request $request, Account $account): \Illuminate\Http\RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($account->customer_id === $customer->id, 403);

        $data = $request->validate([
            'low_balance_alert_threshold' => 'nullable|numeric|min:0',
        ]);

        $account->update([
            'low_balance_alert_threshold' => $data['low_balance_alert_threshold'] ?? null,
            'low_balance_alerted'         => false,
        ]);

        return back()->with('success', $data['low_balance_alert_threshold']
            ? 'Low-balance alert set.'
            : 'Low-balance alert removed.');
    }

    public function statement(Request $request, Account $account): HttpResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($account->customer_id === $customer->id, 403);

        $account->load(['customer', 'branch.manager', 'openedBy.roles']);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $transactions = Transaction::where('account_id', $account->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('processedBy')
            ->orderBy('created_at')
            ->get();

        $pdf = Pdf::loadView('statements.account-statement', compact('account', 'transactions', 'from', 'to'))
            ->setPaper('a4', 'portrait');

        $filename = "statement-{$account->account_number}-{$from}-{$to}.pdf";

        return $pdf->download($filename);
    }
}
