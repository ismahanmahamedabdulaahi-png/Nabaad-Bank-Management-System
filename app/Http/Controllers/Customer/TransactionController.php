<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $customer   = Auth::guard('customer')->user();
        $accountIds = $customer->accounts()->pluck('id');

        $transactions = Transaction::with('account')
            ->whereIn('account_id', $accountIds)
            ->where('status', 'completed')
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->type,       fn ($q, $t)  => $q->where('type', $t))
            ->when($request->date_from,  fn ($q, $d)  => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to,    fn ($q, $d)  => $q->whereDate('created_at', '<=', $d))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('account', fn ($q) => $q->where('account_number', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $accounts = $customer->accounts()->get(['id', 'account_number', 'account_type']);

        return Inertia::render('Customer/Transactions/Index', [
            'transactions' => $transactions,
            'accounts'     => $accounts,
            'filters'      => $request->only(['account_id', 'type', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function show(Transaction $transaction): Response
    {
        $customer   = Auth::guard('customer')->user();
        $accountIds = $customer->accounts()->pluck('id');
        abort_unless($accountIds->contains($transaction->account_id), 403);

        $transaction->load(['account', 'relatedAccount', 'processedBy']);

        return Inertia::render('Customer/Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    public function receipt(Transaction $transaction): HttpResponse
    {
        $customer   = Auth::guard('customer')->user();
        $accountIds = $customer->accounts()->pluck('id');
        abort_unless($accountIds->contains($transaction->account_id), 403);

        $transaction->load(['account.customer', 'relatedAccount', 'processedBy']);

        $pdf = Pdf::loadView('receipts.transaction', compact('transaction'))
            ->setPaper([0, 0, 340, 520], 'portrait');

        return $pdf->download("receipt_{$transaction->reference}.pdf");
    }
}
