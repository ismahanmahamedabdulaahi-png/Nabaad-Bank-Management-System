<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepositRequest;
use App\Http\Requests\Admin\ReversalRequest;
use App\Http\Requests\Admin\TransferRequest;
use App\Http\Requests\Admin\WithdrawalRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\TransactionApproval;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $filters = $request->only(['search', 'type', 'status', 'date_from', 'date_to', 'sort', 'direction']);

        $query = Transaction::with(['account.customer', 'relatedAccount.customer', 'processedBy'])
            ->whereNotNull('account_id');

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', "%{$s}%")
                  ->orWhereHas('account', fn ($a) =>
                        $a->where('account_number', 'like', "%{$s}%")
                          ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"))
                  );
            });
        }

        if (!empty($filters['type']))   $query->where('type',   $filters['type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        $sortable  = ['created_at', 'amount', 'type', 'status'];
        $sort      = in_array($filters['sort'] ?? '', $sortable) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $transactions = $query->orderBy($sort, $direction)->paginate(20)->withQueryString();

        $today = now()->toDateString();
        $stats = [
            'today_count'    => Transaction::whereDate('created_at', $today)->where('status', 'completed')->count(),
            'today_deposits' => Transaction::whereDate('created_at', $today)->where('type', 'deposit')->where('status', 'completed')->sum('amount'),
            'today_withdrawals' => Transaction::whereDate('created_at', $today)->where('type', 'withdrawal')->where('status', 'completed')->sum('amount'),
            'pending'        => Transaction::where('status', 'pending')->count(),
        ];

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'filters'      => $filters,
            'stats'        => $stats,
        ]);
    }

    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account.customer', 'relatedAccount.customer', 'processedBy', 'reversalOf']);

        $reversal = Transaction::where('reversal_of', $transaction->id)->with('processedBy')->first();

        $approvals = TransactionApproval::where('transaction_id', $transaction->id)
            ->with('approver')
            ->orderBy('level')
            ->get();

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => array_merge($transaction->toArray(), [
                'direction' => $transaction->balance_after >= $transaction->balance_before ? 'credit' : 'debit',
                'reversal'  => $reversal,
                'approvals' => $approvals,
            ]),
        ]);
    }

    // ── Deposit ───────────────────────────────────────────────────────────────

    public function depositForm(Request $request): Response
    {
        $this->authorize('deposit', Transaction::class);

        $preselected = null;
        if ($request->account_id) {
            $preselected = Account::with('customer')->where('id', $request->account_id)->where('status', 'active')->first();
        }

        return Inertia::render('Admin/Transactions/Deposit', [
            'accounts'    => $this->activeAccounts(),
            'preselected' => $preselected,
        ]);
    }

    public function deposit(DepositRequest $request): RedirectResponse
    {
        $this->authorize('deposit', Transaction::class);

        $account = Account::findOrFail($request->validated()['account_id']);
        $txn     = $this->service->deposit($account, $request->validated());
        AuditLog::record('created', 'transactions', "Deposit {$txn->reference}: {$txn->currency} {$txn->amount} → {$account->account_number}");

        return redirect()->route('admin.transactions.show', $txn)
            ->with('success', "Deposit of {$txn->currency} {$txn->amount} posted. Ref: {$txn->reference}");
    }

    // ── Withdrawal ────────────────────────────────────────────────────────────

    public function withdrawalForm(Request $request): Response
    {
        $this->authorize('withdraw', Transaction::class);

        $preselected = null;
        if ($request->account_id) {
            $preselected = Account::with('customer')->where('id', $request->account_id)->where('status', 'active')->first();
        }

        return Inertia::render('Admin/Transactions/Withdrawal', [
            'accounts'    => $this->activeAccounts(),
            'preselected' => $preselected,
        ]);
    }

    public function withdrawal(WithdrawalRequest $request): RedirectResponse
    {
        $this->authorize('withdraw', Transaction::class);

        $account = Account::findOrFail($request->validated()['account_id']);
        $txn     = $this->service->withdraw($account, $request->validated());
        AuditLog::record('created', 'transactions', "Withdrawal {$txn->reference}: {$txn->currency} {$txn->amount} ← {$account->account_number}");

        return redirect()->route('admin.transactions.show', $txn)
            ->with('success', "Withdrawal of {$txn->currency} {$txn->amount} posted. Ref: {$txn->reference}");
    }

    // ── Transfer ──────────────────────────────────────────────────────────────

    public function transferForm(): Response
    {
        $this->authorize('transfer', Transaction::class);

        return Inertia::render('Admin/Transactions/Transfer', [
            'accounts' => $this->activeAccounts(),
        ]);
    }

    public function transfer(TransferRequest $request): RedirectResponse
    {
        $this->authorize('transfer', Transaction::class);

        $from = Account::findOrFail($request->validated()['from_account_id']);
        $to   = Account::findOrFail($request->validated()['to_account_id']);

        [$fromTxn] = $this->service->transfer($from, $to, $request->validated());
        AuditLog::record('created', 'transactions', "Transfer {$fromTxn->reference}: {$fromTxn->currency} {$fromTxn->amount} {$from->account_number} → {$to->account_number}");

        return redirect()->route('admin.transactions.show', $fromTxn)
            ->with('success', "Transfer of {$fromTxn->currency} {$fromTxn->amount} completed. Ref: {$fromTxn->reference}");
    }

    // ── Reversal ──────────────────────────────────────────────────────────────

    public function reverse(ReversalRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('reverse', $transaction);

        $reversal = $this->service->reverse($transaction, $request->validated()['reason']);
        AuditLog::record('updated', 'transactions', "Reversal {$reversal->reference} of {$transaction->reference}");

        return redirect()->route('admin.transactions.show', $reversal)
            ->with('success', "Transaction {$transaction->reference} reversed. New ref: {$reversal->reference}");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function activeAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return Account::with('customer')
            ->where('status', 'active')
            ->select('id', 'account_number', 'customer_id', 'account_type', 'balance', 'minimum_balance', 'currency', 'branch_id')
            ->orderBy('account_number')
            ->get();
    }
}
