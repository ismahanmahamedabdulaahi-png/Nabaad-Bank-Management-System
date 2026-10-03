<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChequeActionRequest;
use App\Http\Requests\Admin\IssueChequeBookRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\Customer;
use App\Services\ChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChequeController extends Controller
{
    public function __construct(private ChequeService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChequeBook::class);

        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        $books = ChequeBook::with(['customer', 'account', 'issuedBy'])
            ->when($filters['search'] ?? null, fn ($q, $s) =>
                $q->where('book_number', 'like', "%{$s}%")
                  ->orWhere('series_start', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"))
            )
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '<=', $d))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_books'   => ChequeBook::count(),
            'active_books'  => ChequeBook::where('status', 'active')->count(),
            'paid_today'    => Cheque::where('status', 'paid')->whereDate('cleared_at', today())->count(),
            'deposited_today'=> Cheque::where('status', 'deposited')->whereDate('cleared_at', today())->count(),
            'bounced_total' => Cheque::where('status', 'bounced')->count(),
        ];

        return Inertia::render('Admin/Cheques/Index', [
            'books'   => $books,
            'filters' => $filters,
            'stats'   => $stats,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('issue', ChequeBook::class);

        $customers = Customer::where('status', 'active')
            ->whereHas('accounts', fn ($q) => $q->where('status', 'active'))
            ->get(['id', 'name', 'customer_number']);

        return Inertia::render('Admin/Cheques/Create', [
            'customers'      => $customers,
            'default_leaves' => (int) $this->service->setting('cheque_book_leaves', 25),
        ]);
    }

    public function store(IssueChequeBookRequest $request): RedirectResponse
    {
        $this->authorize('issue', ChequeBook::class);

        $customer = Customer::findOrFail($request->customer_id);
        $account  = Account::findOrFail($request->account_id);

        $book = $this->service->issueBook($customer, $account, $request->validated());

        return redirect()->route('admin.cheques.show', $book)
            ->with('success', "Cheque book {$book->book_number} issued ({$book->series_start}–{$book->series_end}).");
    }

    public function show(ChequeBook $cheque): Response
    {
        $this->authorize('view', $cheque);

        $cheque->load(['customer', 'account', 'issuedBy', 'cheques.processedBy', 'cheques.beneficiaryAccount']);

        // All active accounts for deposit picker
        $allAccounts = Account::where('status', 'active')
            ->with('customer:id,name')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency', 'customer_id']);

        return Inertia::render('Admin/Cheques/Show', [
            'book'        => $cheque,
            'all_accounts'=> $allAccounts,
            'server_date' => now()->toDateString(),
        ]);
    }

    public function verify(Request $request): Response
    {
        $this->authorize('verify', ChequeBook::class);

        $result = null;
        if ($request->q) {
            $result = $this->service->verify(strtoupper(trim($request->q)));
        }

        // All active accounts for deposit picker
        $allAccounts = Account::where('status', 'active')
            ->with('customer:id,name')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency', 'customer_id']);

        return Inertia::render('Admin/Cheques/Verify', [
            'result'       => $result,
            'query'        => $request->q ?? '',
            'all_accounts' => $allAccounts,
            'server_date'  => now()->toDateString(),
        ]);
    }

    // ── Scenario 1: Cash Encashment ───────────────────────────────────────────

    public function encash(Request $request, Cheque $cheque): RedirectResponse
    {
        $this->authorize('cash', ChequeBook::class);

        $data = $request->validate([
            'amount'     => 'required|numeric|min:0.01',
            'payee_name' => 'required|string|max:191',
            'notes'      => 'nullable|string|max:500',
        ]);

        $this->service->encashCheque($cheque, $data);

        AuditLog::record('cheque_encashed', 'cheques',
            "Cheque {$cheque->cheque_number} encashed. USD {$data['amount']} paid to {$data['payee_name']}.");

        return back()->with('success', "Cheque {$cheque->cheque_number} encashed. USD {$data['amount']} paid to {$data['payee_name']}.");
    }

    // ── Scenario 2: Account Deposit ───────────────────────────────────────────

    public function deposit(Request $request, Cheque $cheque): RedirectResponse
    {
        $this->authorize('cash', ChequeBook::class);

        $data = $request->validate([
            'amount'                => 'required|numeric|min:0.01',
            'payee_name'            => 'nullable|string|max:191',
            'beneficiary_account_id'=> 'required|uuid|exists:accounts,id',
            'notes'                 => 'nullable|string|max:500',
        ]);

        $this->service->depositCheque($cheque, $data);

        AuditLog::record('cheque_deposited', 'cheques',
            "Cheque {$cheque->cheque_number} submitted for clearing to account {$data['beneficiary_account_id']}.");

        return back()->with('success', "Cheque {$cheque->cheque_number} deposited to beneficiary account.");
    }

    // ── Legacy: Cancel (Stop Payment) ────────────────────────────────────────

    public function cancel(ChequeActionRequest $request, Cheque $cheque): RedirectResponse
    {
        $this->authorize('cancel', ChequeBook::class);

        $this->service->cancelCheque($cheque, $request->reason);

        return back()->with('success', "Cheque {$cheque->cheque_number} cancelled (stop payment).");
    }

    // ── Legacy: Bounce ────────────────────────────────────────────────────────

    public function bounce(ChequeActionRequest $request, Cheque $cheque): RedirectResponse
    {
        $this->authorize('verify', ChequeBook::class);

        $this->service->bounceCheque($cheque, $request->reason);

        return back()->with('success', "Cheque {$cheque->cheque_number} marked as bounced. Account credited back.");
    }

    // ── Legacy: Manual Clear ──────────────────────────────────────────────────

    public function clear(Request $request, Cheque $cheque): RedirectResponse
    {
        $this->authorize('cash', ChequeBook::class);

        $this->service->clearCheque($cheque);

        return back()->with('success', "Cheque {$cheque->cheque_number} marked as cleared.");
    }

    // ── JSON: Accounts for a customer ─────────────────────────────────────────

    public function accounts(Customer $customer): \Illuminate\Http\JsonResponse
    {
        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type', 'balance']);

        return response()->json($accounts);
    }
}
