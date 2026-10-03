<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Complaint;
use App\Models\Transaction;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $service) {}

    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        $complaints = Complaint::where('customer_id', $customer->id)->latest()->get();

        return Inertia::render('Customer/Complaints/Index', ['complaints' => $complaints]);
    }

    public function create(): Response
    {
        $customer = Auth::guard('customer')->user();

        $accounts = Account::where('customer_id', $customer->id)->get(['id', 'account_number']);
        $transactions = Transaction::whereIn('account_id', $accounts->pluck('id'))
            ->latest()->limit(50)->get(['id', 'reference', 'type', 'amount', 'created_at']);

        return Inertia::render('Customer/Complaints/Create', [
            'accounts'     => $accounts,
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'category'                => ['required', 'in:transaction_dispute,service_quality,account_issue,fraud_report,other'],
            'subject'                 => ['required', 'string', 'max:150'],
            'description'             => ['required', 'string', 'max:3000'],
            'related_account_id'      => ['nullable', 'exists:accounts,id'],
            'related_transaction_id'  => ['nullable', 'exists:transactions,id'],
        ]);

        if (!empty($data['related_account_id'])) {
            $account = Account::findOrFail($data['related_account_id']);
            abort_unless($account->customer_id === $customer->id, 403);
        }

        if (!empty($data['related_transaction_id'])) {
            $accountIds = $customer->accounts()->pluck('id');
            $transaction = Transaction::findOrFail($data['related_transaction_id']);
            abort_unless($accountIds->contains($transaction->account_id), 403);
        }

        $complaint = $this->service->file($customer, $data);

        return redirect()->route('customer.complaints.show', $complaint->id)
            ->with('success', "Complaint {$complaint->complaint_number} submitted.");
    }

    public function show(Complaint $complaint): Response
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($complaint->customer_id === $customer->id, 403);

        return Inertia::render('Customer/Complaints/Show', [
            'complaint' => $complaint->load(['relatedAccount', 'relatedTransaction']),
        ]);
    }
}
