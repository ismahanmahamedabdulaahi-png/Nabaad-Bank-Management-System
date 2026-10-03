<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Services\GeneralLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GeneralLedgerController extends Controller
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    public function index(): \Inertia\Response
    {
        $this->authorize('gl.view');

        return Inertia::render('Admin/GeneralLedger/Index', [
            'trial_balance'  => $this->gl->trialBalance(),
            'reconciliation' => $this->gl->reconciliationReport(),
            'recent_entries' => JournalEntry::with('lines.glAccount')
                ->latest('entry_date')->latest('created_at')
                ->limit(8)->get(),
        ]);
    }

    // ── Chart of Accounts ────────────────────────────────────────────────────

    public function chartOfAccounts(): \Inertia\Response
    {
        $this->authorize('gl.view');

        return Inertia::render('Admin/GeneralLedger/ChartOfAccounts', [
            'accounts' => GlAccount::orderBy('code')->get()->map(fn (GlAccount $a) => [
                ...$a->toArray(),
                'balance' => $a->balance(),
            ]),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorize('gl.manage-accounts');

        $data = $request->validate([
            'code'        => 'required|string|max:10|unique:gl_accounts,code',
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:asset,liability,equity,income,expense',
            'description' => 'nullable|string|max:1000',
        ]);

        GlAccount::create([
            ...$data,
            'normal_balance' => in_array($data['type'], ['asset', 'expense']) ? 'debit' : 'credit',
            'is_system'      => false,
            'is_active'      => true,
        ]);

        return back()->with('success', "GL account {$data['code']} created.");
    }

    // ── Journal Entries ──────────────────────────────────────────────────────

    public function journalEntries(Request $request): \Inertia\Response
    {
        $this->authorize('gl.view');

        $entries = JournalEntry::with(['lines.glAccount', 'createdBy:id,name'])
            ->when($request->date_from, fn ($q, $v) => $q->where('entry_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->where('entry_date', '<=', $v))
            ->when($request->source_type, fn ($q, $v) => $q->where('source_type', $v))
            ->when($request->gl_account_id, fn ($q, $v) => $q->whereHas(
                'lines', fn ($q2) => $q2->where('gl_account_id', $v)
            ))
            ->orderByDesc('entry_date')->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/GeneralLedger/JournalEntries/Index', [
            'entries' => $entries,
            'accounts' => GlAccount::orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $request->only(['date_from', 'date_to', 'gl_account_id', 'source_type']),
        ]);
    }

    public function showJournalEntry(JournalEntry $journalEntry): \Inertia\Response
    {
        $this->authorize('gl.view');

        $journalEntry->load(['lines.glAccount', 'createdBy:id,name', 'reversedEntry']);

        return Inertia::render('Admin/GeneralLedger/JournalEntries/Show', [
            'entry' => $journalEntry,
        ]);
    }

    public function createJournalEntry(): \Inertia\Response
    {
        $this->authorize('gl.post');

        return Inertia::render('Admin/GeneralLedger/JournalEntries/Create', [
            'accounts' => GlAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function storeJournalEntry(Request $request): RedirectResponse
    {
        $this->authorize('gl.post');

        $data = $request->validate([
            'entry_date'             => 'required|date',
            'description'            => 'required|string|max:255',
            'lines'                  => 'required|array|min:2',
            'lines.*.gl_account_id'  => 'required|exists:gl_accounts,id',
            'lines.*.debit'          => 'nullable|numeric|min:0',
            'lines.*.credit'         => 'nullable|numeric|min:0',
            'lines.*.memo'           => 'nullable|string|max:255',
        ]);

        $lines = collect($data['lines'])->map(fn (array $l) => [
            'account' => GlAccount::findOrFail($l['gl_account_id']),
            'debit'   => $l['debit'] ?? 0,
            'credit'  => $l['credit'] ?? 0,
            'memo'    => $l['memo'] ?? null,
        ])->all();

        try {
            $entry = $this->gl->post(
                $lines,
                $data['description'],
                'manual',
                null,
                Carbon::parse($data['entry_date']),
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['lines' => $e->getMessage()]);
        }

        return redirect()->route('admin.general-ledger.journal-entries.show', $entry)
            ->with('success', "Journal entry {$entry->entry_number} posted.");
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    public function reports(Request $request): \Inertia\Response
    {
        $this->authorize('gl.view');

        $asOf = $request->as_of ?: today()->toDateString();
        $from = $request->date_from ?: today()->startOfMonth()->toDateString();
        $to   = $request->date_to ?: today()->toDateString();

        return Inertia::render('Admin/GeneralLedger/Reports', [
            'as_of'           => $asOf,
            'date_from'       => $from,
            'date_to'         => $to,
            'trial_balance'   => $this->gl->trialBalance($asOf),
            'balance_sheet'   => $this->gl->balanceSheet($asOf),
            'income_statement'=> $this->gl->incomeStatement($from, $to),
        ]);
    }

    public function exportTrialBalance(Request $request)
    {
        $this->authorize('gl.view');
        $asOf = $request->as_of ?: today()->toDateString();
        $rows = $this->gl->trialBalance($asOf);

        $pdf = Pdf::loadView('reports.general-ledger', [
            'title' => 'Trial Balance',
            'asOf'  => $asOf,
            'rows'  => $rows,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("trial_balance_{$asOf}.pdf");
    }

    public function exportBalanceSheet(Request $request)
    {
        $this->authorize('gl.view');
        $asOf = $request->as_of ?: today()->toDateString();
        $sheet = $this->gl->balanceSheet($asOf);

        $pdf = Pdf::loadView('reports.general-ledger-balance-sheet', [
            'asOf'  => $asOf,
            'sheet' => $sheet,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("balance_sheet_{$asOf}.pdf");
    }
}
