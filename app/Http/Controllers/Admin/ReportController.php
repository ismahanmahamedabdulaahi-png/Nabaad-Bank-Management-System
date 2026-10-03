<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ChequesExport;
use App\Exports\LoansExport;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Cheque;
use App\Models\Loan;
use App\Models\TellerTill;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'accounts' => Account::where('status', 'active')
                ->with('customer:id,name')
                ->get(['id', 'account_number', 'account_type', 'customer_id']),
        ]);
    }

    // ── Transaction Report ────────────────────────────────────────────────────

    public function transactions(Request $request): mixed
    {
        $data = $request->validate([
            'date_from'  => 'required|date',
            'date_to'    => 'required|date|after_or_equal:date_from',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'type'       => 'nullable|in:deposit,withdrawal,transfer,reversal',
            'format'     => 'required|in:pdf,excel',
        ]);

        $txns = Transaction::with(['account.customer', 'processedBy', 'relatedAccount'])
            ->whereBetween('created_at', [$data['date_from'] . ' 00:00:00', $data['date_to'] . ' 23:59:59'])
            ->when($data['account_id'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->get();

        $totals = [
            'deposits'    => $txns->where('type', 'deposit')->sum('amount'),
            'withdrawals' => $txns->where('type', 'withdrawal')->sum('amount'),
            'transfers'   => $txns->where('type', 'transfer')->sum('amount'),
            'all'         => $txns->sum('amount'),
            'net'         => $txns->where('type', 'deposit')->sum('amount') - $txns->where('type', 'withdrawal')->sum('amount'),
        ];

        $accountFilter = isset($data['account_id'])
            ? Account::find($data['account_id'])?->account_number
            : null;

        if ($data['format'] === 'excel') {
            return Excel::download(
                new TransactionsExport($txns),
                "transactions_{$data['date_from']}_{$data['date_to']}.xlsx"
            );
        }

        $pdf = Pdf::loadView('reports.transactions', [
            'transactions'  => $txns,
            'totals'        => $totals,
            'dateFrom'      => $data['date_from'],
            'dateTo'        => $data['date_to'],
            'accountFilter' => $accountFilter,
            'typeFilter'    => $data['type'] ?? null,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("transactions_{$data['date_from']}.pdf");
    }

    // ── Teller Till Summary ───────────────────────────────────────────────────

    public function tellerSummary(Request $request): mixed
    {
        $data = $request->validate([
            'date'      => 'required|date',
            'teller_id' => 'nullable|exists:users,id',
            'format'    => 'required|in:pdf,excel',
        ]);

        $tills = TellerTill::with(['teller', 'movements.processedBy'])
            ->whereDate('business_date', $data['date'])
            ->when($data['teller_id'] ?? null, fn ($q, $id) => $q->where('teller_id', $id))
            ->get();

        $tellerName = isset($data['teller_id'])
            ? \App\Models\User::find($data['teller_id'])?->name
            : null;

        if ($data['format'] === 'excel') {
            // Simple flat Excel for teller summary
            $rows = collect();
            foreach ($tills as $till) {
                $rows->push([
                    $till->till_name,
                    $till->teller?->name,
                    $till->status,
                    number_format($till->opening_balance, 2),
                    number_format($till->expected_balance, 2),
                    number_format($till->closing_balance ?? $till->current_balance, 2),
                    number_format(($till->closing_balance ?? $till->current_balance) - $till->expected_balance, 2),
                ]);
            }

            return Excel::download(
                new class($rows) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
                    public function __construct(private $data) {}
                    public function collection() { return $this->data; }
                    public function headings(): array { return ['Till', 'Teller', 'Status', 'Opening', 'Expected', 'Closing', 'Variance']; }
                },
                "teller_summary_{$data['date']}.xlsx"
            );
        }

        $pdf = Pdf::loadView('reports.teller-summary', [
            'tills'  => $tills,
            'date'   => $data['date'],
            'teller' => $tellerName,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("teller_summary_{$data['date']}.pdf");
    }

    // ── Loan Portfolio Report ─────────────────────────────────────────────────

    public function loans(Request $request): mixed
    {
        $data = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'status'    => 'nullable|in:active,closed,overdue,defaulted,pending_approval',
            'format'    => 'required|in:pdf,excel',
        ]);

        $loans = Loan::with(['customer', 'account'])
            ->whereBetween('created_at', [$data['date_from'] . ' 00:00:00', $data['date_to'] . ' 23:59:59'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('created_at')
            ->get();

        $totals = [
            'disbursed'    => $loans->sum('amount'),
            'outstanding'  => $loans->sum('outstanding_balance'),
            'paid'         => $loans->sum('total_paid'),
            'overdue_count'=> $loans->where('status', 'overdue')->count(),
        ];

        if ($data['format'] === 'excel') {
            return Excel::download(new LoansExport($loans), "loans_{$data['date_from']}_{$data['date_to']}.xlsx");
        }

        $pdf = Pdf::loadView('reports.loans', [
            'loans'        => $loans,
            'totals'       => $totals,
            'dateFrom'     => $data['date_from'],
            'dateTo'       => $data['date_to'],
            'statusFilter' => $data['status'] ?? null,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("loans_{$data['date_from']}.pdf");
    }

    // ── Cheque Register Report ────────────────────────────────────────────────

    public function cheques(Request $request): mixed
    {
        $data = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'status'    => 'nullable|in:issued,paid,deposited,bounced,cancelled,pending_clearance,cleared',
            'format'    => 'required|in:pdf,excel',
        ]);

        $cheques = Cheque::with(['account.customer', 'chequeBook', 'processedBy'])
            ->whereBetween('created_at', [$data['date_from'] . ' 00:00:00', $data['date_to'] . ' 23:59:59'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('created_at')
            ->get();

        $stats = [
            'issued'      => $cheques->where('status', 'issued')->count(),
            'paid'        => $cheques->where('status', 'paid')->count(),
            'deposited'   => $cheques->where('status', 'deposited')->count(),
            'bounced'     => $cheques->where('status', 'bounced')->count(),
            'cancelled'   => $cheques->where('status', 'cancelled')->count(),
            'total_value' => $cheques->whereNotNull('amount')->sum('amount'),
        ];

        if ($data['format'] === 'excel') {
            return Excel::download(new ChequesExport($cheques), "cheques_{$data['date_from']}_{$data['date_to']}.xlsx");
        }

        $pdf = Pdf::loadView('reports.cheques', [
            'cheques'      => $cheques,
            'stats'        => $stats,
            'dateFrom'     => $data['date_from'],
            'dateTo'       => $data['date_to'],
            'statusFilter' => $data['status'] ?? null,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("cheques_{$data['date_from']}.pdf");
    }

    // ── Transaction Receipt ───────────────────────────────────────────────────

    public function receipt(Transaction $transaction): Response
    {
        $transaction->load(['account.customer', 'relatedAccount', 'processedBy']);

        $pdf = Pdf::loadView('receipts.transaction', compact('transaction'))
            ->setPaper([0, 0, 340, 520], 'portrait');

        return $pdf->stream("receipt_{$transaction->reference}.pdf");
    }

    // ── Loan Disbursement Letter ──────────────────────────────────────────────

    public function loanLetter(Loan $loan): Response
    {
        $loan->load(['customer', 'account']);

        $pdf = Pdf::loadView('receipts.loan-disbursement', compact('loan'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("loan_{$loan->loan_number}.pdf");
    }

    // ── Account Statement ─────────────────────────────────────────────────────

    public function accountStatement(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', Account::class);

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
