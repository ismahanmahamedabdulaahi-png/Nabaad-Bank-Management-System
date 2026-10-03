<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\ComplianceCase;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\ComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ComplianceController extends Controller
{
    public function __construct(private ComplianceService $service) {}

    public function index(Request $request): Response
    {
        abort_unless(Auth::user()->can('compliance.view'), 403);

        $cases = ComplianceCase::with(['customer', 'flaggedBy'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->severity, fn ($q, $s) => $q->where('severity', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Compliance/Index', [
            'cases'   => $cases,
            'filters' => $request->only(['status', 'type', 'severity']),
        ]);
    }

    public function show(ComplianceCase $complianceCase): Response
    {
        abort_unless(Auth::user()->can('compliance.view'), 403);

        return Inertia::render('Admin/Compliance/Show', [
            'case' => $complianceCase->load(['customer', 'transaction.account', 'flaggedBy']),
        ]);
    }

    public function flag(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('compliance.manage'), 403);

        $data = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'transaction_id' => ['nullable', 'exists:transactions,id'],
            'notes'          => ['required', 'string', 'max:2000'],
            'severity'       => ['nullable', 'in:low,medium,high'],
        ]);

        $customer    = Customer::findOrFail($data['customer_id']);
        $transaction = !empty($data['transaction_id']) ? Transaction::find($data['transaction_id']) : null;

        $this->service->flagManually($customer, $transaction, $data['notes'], Auth::id(), $data['severity'] ?? 'medium');

        return back()->with('success', 'Flagged for compliance review.');
    }

    public function update(Request $request, ComplianceCase $complianceCase): RedirectResponse
    {
        abort_unless(Auth::user()->can('compliance.manage'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:open,reviewing,cleared,reported'],
            'notes'  => ['nullable', 'string', 'max:3000'],
        ]);

        $this->service->updateCase($complianceCase, $data['status'], $data['notes'] ?? null);

        return back()->with('success', 'Case updated.');
    }

    public function largeTransactions(Request $request)
    {
        abort_unless(Auth::user()->can('compliance.view'), 403);

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'format'    => ['nullable', 'in:excel'],
        ]);

        $from = $data['date_from'] ?? today()->subDays(30)->toDateString();
        $to   = $data['date_to']   ?? today()->toDateString();

        $transactions = $this->service->largeTransactions($from, $to);

        if (($data['format'] ?? null) === 'excel') {
            return Excel::download(new TransactionsExport($transactions), "large-transactions_{$from}_{$to}.xlsx");
        }

        return Inertia::render('Admin/Compliance/LargeTransactions', [
            'transactions' => $transactions,
            'threshold'    => $this->service->largeTransactionThreshold(),
            'date_from'    => $from,
            'date_to'      => $to,
        ]);
    }
}
