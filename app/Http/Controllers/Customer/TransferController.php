<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function create(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $myAccounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency']);

        $allAccounts = Account::where('status', 'active')
            ->with('customer:id,name')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency', 'customer_id']);

        $preselectedId = $myAccounts->firstWhere('id', $request->query('source_account_id'))?->id;

        return Inertia::render('Customer/Transfers/Create', [
            'my_accounts'  => $myAccounts,
            'all_accounts' => $allAccounts,
            'preselected_source_account_id' => $preselectedId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'from_account_id' => ['required', 'exists:accounts,id'],
            'to_account_id'   => ['required', 'exists:accounts,id', 'different:from_account_id'],
            'amount'          => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'description'     => ['nullable', 'string', 'max:255'],
        ], [
            'to_account_id.different' => 'Source and destination accounts must be different.',
        ]);

        $from = Account::findOrFail($data['from_account_id']);
        abort_unless($from->customer_id === $customer->id, 403, 'That account does not belong to you.');

        $to = Account::findOrFail($data['to_account_id']);

        [$fromTxn] = $this->service->transfer($from, $to, $data);

        // Self-service activity is tagged so AML/reporting can tell it apart
        // from teller-initiated transfers without changing the shared service.
        Transaction::where('reference', $fromTxn->reference)
            ->orWhere('reference', $fromTxn->reference . '-CR')
            ->update(['initiated_by_customer_id' => $customer->id]);

        AuditLog::record('created', 'transactions',
            "Self-service transfer {$fromTxn->reference}: {$fromTxn->currency} {$fromTxn->amount} {$from->account_number} → {$to->account_number}");

        $message = $fromTxn->status === 'pending'
            ? "Transfer of {$fromTxn->currency} {$fromTxn->amount} submitted and is awaiting approval. Ref: {$fromTxn->reference}"
            : "Transfer of {$fromTxn->currency} {$fromTxn->amount} completed. Ref: {$fromTxn->reference}";

        return redirect()->route('customer.accounts.show', $from->id)->with('success', $message);
    }
}
