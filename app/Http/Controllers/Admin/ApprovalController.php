<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovalActionRequest;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $user      = Auth::user();
        $myLevel   = $this->service->userApprovalLevel($user);
        $isSuperAdmin = $user->hasRole('Super Admin');
        $filters   = $request->only(['search', 'type', 'view']);
        $view      = $filters['view'] ?? 'mine';

        $query = Transaction::with(['account.customer', 'processedBy'])
            ->where('status', 'pending')
            ->where('requires_approval', true);

        if ($view === 'mine') {
            $query->where('processed_by', '!=', $user->id);

            if ($isSuperAdmin) {
                $query->whereColumn('approval_level_reached', '<', 'approval_level_required');
            } else {
                $query->where('approval_level_required', '>=', $myLevel)
                    ->where('approval_level_reached', $myLevel - 1);
            }
        }

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

        if (!empty($filters['type'])) $query->where('type', $filters['type']);

        if ($view === 'escalated') {
            $query->whereNotNull('escalated_at');
        }

        $pending = $query->orderBy('created_at')->paginate(20)->withQueryString();

        $myQueue = Transaction::where('status', 'pending')
                ->where('requires_approval', true)
                ->where('processed_by', '!=', $user->id);
        if ($isSuperAdmin) {
            $myQueue->whereColumn('approval_level_reached', '<', 'approval_level_required');
        } else {
            $myQueue->where('approval_level_required', '>=', $myLevel)
                ->where('approval_level_reached', $myLevel - 1);
        }

        $stats = [
            'my_queue'    => $myQueue->count(),
            'all_pending' => Transaction::where('status', 'pending')->where('requires_approval', true)->count(),
            'escalated'   => Transaction::where('status', 'pending')->where('requires_approval', true)->whereNotNull('escalated_at')->count(),
            'stale'       => Transaction::where('status', 'pending')->where('requires_approval', true)
                ->where('created_at', '<', now()->subHours(24))->count(),
            'my_level'    => $myLevel,
        ];

        return Inertia::render('Admin/Approvals/Index', [
            'pending' => $pending,
            'filters' => $filters,
            'stats'   => $stats,
        ]);
    }

    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load([
            'account.customer',
            'relatedAccount.customer',
            'processedBy',
            'approvals.approver',
            'reversalOf',
        ]);

        $user    = Auth::user();
        $myLevel = $this->service->userApprovalLevel($user);
        $isOwnTransaction = $transaction->getRawOriginal('processed_by') == $user->id;

        $isSuperAdmin = $user->hasRole('Super Admin');
        $nextLevel = $isSuperAdmin
            ? max($transaction->approval_level_reached + 1, $transaction->approval_level_required)
            : $transaction->approval_level_reached + 1;

        return Inertia::render('Admin/Approvals/Show', [
            'transaction' => $transaction,
            'my_level'    => $myLevel,
            'can_act'     => ($isSuperAdmin || $myLevel === $nextLevel)
                             && $transaction->approval_level_required >= $nextLevel
                             && !$isOwnTransaction
                             && $transaction->status === 'pending',
            'is_own_transaction' => $isOwnTransaction,
            'next_level'  => $nextLevel,
            'is_stale'    => $transaction->isStale(),
        ]);
    }

    public function escalate(Transaction $transaction): RedirectResponse
    {
        $this->authorize('view', $transaction);

        if ($transaction->status !== 'pending' || !$transaction->requires_approval) {
            throw ValidationException::withMessages([
                'transaction' => 'This transaction is not pending approval.',
            ]);
        }

        if ($transaction->escalated_at) {
            return back()->with('success', "Transaction {$transaction->reference} is already escalated.");
        }

        $transaction->update(['escalated_at' => now()]);

        AuditLog::record('escalated', 'transactions',
            "Transaction {$transaction->reference} manually escalated by " . Auth::user()->name . " — flagged for urgent attention.");

        return back()->with('success', "Transaction {$transaction->reference} has been escalated for urgent attention.");
    }

    public function approve(ApprovalActionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('view', $transaction);

        $this->service->approve($transaction, $request->notes);

        $transaction->refresh();
        $msg = $transaction->status === 'completed'
            ? "Transaction {$transaction->reference} approved and executed."
            : "Transaction {$transaction->reference} approved at level {$transaction->approval_level_reached}.";

        return redirect()->route('admin.approvals.index')->with('success', $msg);
    }

    public function reject(ApprovalActionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('view', $transaction);

        $this->service->reject($transaction, $request->notes ?? '');

        return redirect()->route('admin.approvals.index')
            ->with('success', "Transaction {$transaction->reference} has been rejected.");
    }
}
