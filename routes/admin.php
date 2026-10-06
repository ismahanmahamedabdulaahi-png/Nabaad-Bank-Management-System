<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ForcePasswordChangeController;
use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Http\Controllers\Admin\DeviceSessionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StaffInvitationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StandingOrderController;
use App\Http\Controllers\Admin\TellerController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KycController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BusinessDayController;
use App\Http\Controllers\Admin\ChequeController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\VaultController;
use App\Http\Controllers\Auth\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Auth\Admin\PasswordResetLinkController;
use App\Http\Controllers\Auth\Admin\NewPasswordController;
use App\Http\Controllers\Auth\Admin\TwoFactorController;
use Illuminate\Support\Facades\Route;

// ── Staff Authentication (unauthenticated) ────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// ── Staff Invitation (unauthenticated, protected by the signed link itself) ───
Route::get('/staff/invitations/{user}/accept', [StaffInvitationController::class, 'show'])
    ->middleware('signed')
    ->name('staff-invitation.show');
Route::post('/staff/invitations/{user}/accept', [StaffInvitationController::class, 'accept'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('staff-invitation.accept');

// ── 2FA verification ──────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.verify');
    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
});

// ── Forced password change — reachable once past 2FA but deliberately outside
// the main admin group below, since that group requires passing this very gate ─
Route::middleware(['auth', 'two-factor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/password/change', [ForcePasswordChangeController::class, 'show'])->name('password.change');
        Route::post('/password/change', [ForcePasswordChangeController::class, 'update'])->name('password.change.store');
    });

// ── Staff Authenticated Area ──────────────────────────────────────────────────
Route::middleware(['auth', 'two-factor', 'must-change-password', 'session.timeout:web', 'verified', 'tag-session'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        // Session keep-alive — polled while a long form (e.g. customer/KYC registration) is open,
        // so the session doesn't expire mid-fill. Passes through session.timeout, which bumps activity.
        Route::get('/ping', fn () => response()->noContent())->name('ping');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── User Management ───────────────────────────────────────────────────
        Route::resource('users', UserController::class);
        Route::post('users/{user}/toggle-status',  [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        // ── Roles & Permissions ───────────────────────────────────────────────
        Route::resource('roles', RoleController::class)->except(['show']);

        // ── Customer Management ───────────────────────────────────────────────
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/toggle-status',  [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
        Route::post('customers/{customer}/reset-password', [CustomerController::class, 'resetPassword'])->name('customers.reset-password');
        Route::get('customers/{customer}/photo',     [CustomerController::class, 'servePhoto'])->name('customers.photo');
        Route::get('customers/{customer}/signature', [CustomerController::class, 'serveSignature'])->name('customers.signature');

        // ── Account Management ────────────────────────────────────────────────
        Route::get('accounts',                           [AccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/create',                    [AccountController::class, 'create'])->name('accounts.create');
        Route::post('accounts',                          [AccountController::class, 'store'])->name('accounts.store');
        Route::get('accounts/{account}',                 [AccountController::class, 'show'])->name('accounts.show');
        Route::post('accounts/{account}/freeze',         [AccountController::class, 'freeze'])->name('accounts.freeze');
        Route::post('accounts/{account}/unfreeze',       [AccountController::class, 'unfreeze'])->name('accounts.unfreeze');
        Route::post('accounts/{account}/close',          [AccountController::class, 'close'])->name('accounts.close');
        Route::post('accounts/{account}/reactivate',     [AccountController::class, 'reactivate'])->name('accounts.reactivate');

        // ── Loans ─────────────────────────────────────────────────────────────
        Route::get('loans',                          [LoanController::class, 'index'])->name('loans.index');
        Route::get('loans/create',                   [LoanController::class, 'create'])->name('loans.create');
        Route::post('loans',                         [LoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}',                   [LoanController::class, 'show'])->name('loans.show');
        Route::post('loans/{loan}/review',           [LoanController::class, 'review'])->name('loans.review');
        Route::post('loans/{loan}/approve',          [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('loans/{loan}/reject',           [LoanController::class, 'reject'])->name('loans.reject');
        Route::post('loans/{loan}/disburse',         [LoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/repay',            [LoanController::class, 'repay'])->name('loans.repay');
        Route::get('loans/accounts/{customer}',      [LoanController::class, 'accounts'])->name('loans.accounts');
        Route::get('loans/eligibility/{customer}',   [LoanController::class, 'eligibility'])->name('loans.eligibility');

        // ── Cheques ───────────────────────────────────────────────────────────
        Route::get('cheques',                          [ChequeController::class, 'index'])->name('cheques.index');
        Route::get('cheques/create',                   [ChequeController::class, 'create'])->name('cheques.create');
        Route::get('cheques/verify',                   [ChequeController::class, 'verify'])->name('cheques.verify');
        Route::post('cheques',                         [ChequeController::class, 'store'])->name('cheques.store');
        Route::post('cheques/{cheque}/encash',         [ChequeController::class, 'encash'])->name('cheques.encash');
        Route::post('cheques/{cheque}/deposit',        [ChequeController::class, 'deposit'])->name('cheques.deposit');
        Route::post('cheques/{cheque}/clear',          [ChequeController::class, 'clear'])->name('cheques.clear');
        Route::post('cheques/{cheque}/cancel',         [ChequeController::class, 'cancel'])->name('cheques.cancel');
        Route::post('cheques/{cheque}/bounce',         [ChequeController::class, 'bounce'])->name('cheques.bounce');
        Route::get('cheques/accounts/{customer}',      [ChequeController::class, 'accounts'])->name('cheques.accounts');
        Route::get('cheques/{cheque}',                 [ChequeController::class, 'show'])->name('cheques.show');

        // ── Business Day ──────────────────────────────────────────────────────
        Route::get('business-day',       [BusinessDayController::class, 'index'])->name('business-day.index');
        Route::post('business-day/open', [BusinessDayController::class, 'open'])->name('business-day.open');
        Route::post('business-day/close',[BusinessDayController::class, 'close'])->name('business-day.close');

        // NOTE: "End of Day" and "Public Holidays" admin UI/routes were removed on
        // request (2026-09-10) — hidden from the admin panel, to be reintroduced
        // later. The underlying automation is untouched and keeps running: eod:run
        // still executes on its daily schedule (routes/console.php), still driving
        // standing orders, cheque clearing, loan overdue marking, dormancy, FD
        // maturity, and GL reconciliation. EndOfDayController, PublicHolidayController,
        // their Vue pages, and EodService/EodRun/PublicHoliday all remain in place.

        // ── Vault ─────────────────────────────────────────────────────────────
        Route::get('vault',              [VaultController::class, 'show'])->name('vault.show');
        Route::post('vault/open',        [VaultController::class, 'open'])->name('vault.open');
        Route::post('vault/close',       [VaultController::class, 'close'])->name('vault.close');
        Route::post('vault/cash-in',     [VaultController::class, 'cashIn'])->name('vault.cash-in');
        Route::post('vault/cash-out',    [VaultController::class, 'cashOut'])->name('vault.cash-out');

        // ── Complaints ───────────────────────────────────────────────────────────
        Route::get('complaints',                       [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('complaints/{complaint}',            [ComplaintController::class, 'show'])->name('complaints.show');
        Route::post('complaints/{complaint}/assign',    [ComplaintController::class, 'assign'])->name('complaints.assign');
        Route::post('complaints/{complaint}/status',    [ComplaintController::class, 'updateStatus'])->name('complaints.status');

        // ── Compliance / AML ─────────────────────────────────────────────────────
        Route::get('compliance',                        [ComplianceController::class, 'index'])->name('compliance.index');
        Route::get('compliance/large-transactions',      [ComplianceController::class, 'largeTransactions'])->name('compliance.large-transactions');
        Route::post('compliance/flag',                   [ComplianceController::class, 'flag'])->name('compliance.flag');
        Route::get('compliance/{complianceCase}',         [ComplianceController::class, 'show'])->name('compliance.show');
        Route::post('compliance/{complianceCase}/update', [ComplianceController::class, 'update'])->name('compliance.update');

        // ── Teller Operations ─────────────────────────────────────────────────
        Route::get('tellers',                                    [TellerController::class, 'index'])->name('tellers.index');
        Route::get('tellers/assign',                             [TellerController::class, 'create'])->name('tellers.create');
        Route::post('tellers',                                   [TellerController::class, 'store'])->name('tellers.store');
        Route::get('tellers/{teller}',                           [TellerController::class, 'show'])->name('tellers.show');
        Route::post('tellers/{teller}/close',                    [TellerController::class, 'close'])->name('tellers.close');
        Route::post('tellers/{teller}/replenish',                [TellerController::class, 'replenish'])->name('tellers.replenish');
        Route::post('tellers/{teller}/return',                   [TellerController::class, 'returnCash'])->name('tellers.return');
        Route::post('tellers/{teller}/transfer',                 [TellerController::class, 'transfer'])->name('tellers.transfer');
        Route::post('tellers/{teller}/request-replenishment',    [TellerController::class, 'requestReplenishment'])->name('tellers.request-replenishment');
        Route::post('tellers/{teller}/approve-replenishment',    [TellerController::class, 'approveReplenishment'])->name('tellers.approve-replenishment');
        Route::post('tellers/{teller}/reject-replenishment',     [TellerController::class, 'rejectReplenishment'])->name('tellers.reject-replenishment');

        // ── Settings ──────────────────────────────────────────────────────────
        Route::get('settings',    [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings',    [SettingsController::class, 'update'])->name('settings.update');

        // ── Audit Logs ────────────────────────────────────────────────────────
        Route::get('audit-logs',           [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

        // ── Reports ───────────────────────────────────────────────────────────
        Route::get('reports',                              [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/transactions',                 [ReportController::class, 'transactions'])->name('reports.transactions');
        Route::get('reports/teller-summary',               [ReportController::class, 'tellerSummary'])->name('reports.teller-summary');
        Route::get('reports/loans',                        [ReportController::class, 'loans'])->name('reports.loans');
        Route::get('reports/cheques',                      [ReportController::class, 'cheques'])->name('reports.cheques');
        Route::get('reports/receipt/{transaction}',        [ReportController::class, 'receipt'])->name('reports.receipt');
        Route::get('reports/loan-letter/{loan}',           [ReportController::class, 'loanLetter'])->name('reports.loan-letter');
        Route::get('reports/statement/{account}',          [ReportController::class, 'accountStatement'])->name('reports.statement');

        // ── General Ledger ────────────────────────────────────────────────────
        Route::prefix('general-ledger')->name('general-ledger.')->group(function () {
            Route::get('/',                       [GeneralLedgerController::class, 'index'])->name('index');
            Route::get('chart-of-accounts',        [GeneralLedgerController::class, 'chartOfAccounts'])->name('chart-of-accounts');
            Route::post('chart-of-accounts',       [GeneralLedgerController::class, 'storeAccount'])->name('chart-of-accounts.store');
            Route::get('journal-entries',          [GeneralLedgerController::class, 'journalEntries'])->name('journal-entries.index');
            Route::get('journal-entries/create',   [GeneralLedgerController::class, 'createJournalEntry'])->name('journal-entries.create');
            Route::post('journal-entries',         [GeneralLedgerController::class, 'storeJournalEntry'])->name('journal-entries.store');
            Route::get('journal-entries/{journalEntry}', [GeneralLedgerController::class, 'showJournalEntry'])->name('journal-entries.show');
            Route::get('reports',                  [GeneralLedgerController::class, 'reports'])->name('reports');
            Route::get('reports/trial-balance/export', [GeneralLedgerController::class, 'exportTrialBalance'])->name('reports.trial-balance.export');
            Route::get('reports/balance-sheet/export', [GeneralLedgerController::class, 'exportBalanceSheet'])->name('reports.balance-sheet.export');
        });

        // ── Approvals ─────────────────────────────────────────────────────────
        Route::get('approvals',                              [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('approvals/{transaction}',                [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('approvals/{transaction}/approve',       [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{transaction}/reject',        [ApprovalController::class, 'reject'])->name('approvals.reject');
        Route::post('approvals/{transaction}/escalate',      [ApprovalController::class, 'escalate'])->name('approvals.escalate');

        // ── Transactions ──────────────────────────────────────────────────────
        Route::get('transactions',                          [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/deposit',                  [TransactionController::class, 'depositForm'])->name('transactions.deposit.form');
        Route::post('transactions/deposit',                 [TransactionController::class, 'deposit'])->name('transactions.deposit');
        Route::get('transactions/withdrawal',               [TransactionController::class, 'withdrawalForm'])->name('transactions.withdrawal.form');
        Route::post('transactions/withdrawal',              [TransactionController::class, 'withdrawal'])->name('transactions.withdrawal');
        Route::get('transactions/transfer',                 [TransactionController::class, 'transferForm'])->name('transactions.transfer.form');
        Route::post('transactions/transfer',                [TransactionController::class, 'transfer'])->name('transactions.transfer');
        Route::get('transactions/{transaction}',            [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}/reverse',   [TransactionController::class, 'reverse'])->name('transactions.reverse');

        // ── Standing Orders ───────────────────────────────────────────────────
        Route::get('standing-orders',                       [StandingOrderController::class, 'index'])->name('standing-orders.index');
        Route::get('standing-orders/create',                [StandingOrderController::class, 'create'])->name('standing-orders.create');
        Route::post('standing-orders',                      [StandingOrderController::class, 'store'])->name('standing-orders.store');
        Route::get('standing-orders/{standingOrder}',       [StandingOrderController::class, 'show'])->name('standing-orders.show');
        Route::post('standing-orders/{standingOrder}/pause',  [StandingOrderController::class, 'pause'])->name('standing-orders.pause');
        Route::post('standing-orders/{standingOrder}/resume', [StandingOrderController::class, 'resume'])->name('standing-orders.resume');
        Route::post('standing-orders/{standingOrder}/cancel', [StandingOrderController::class, 'cancel'])->name('standing-orders.cancel');

        // ── KYC Management ────────────────────────────────────────────────────
        Route::get('kyc',                                       [KycController::class, 'index'])->name('kyc.index');
        Route::get('kyc/{kyc}',                                 [KycController::class, 'show'])->name('kyc.show');
        Route::post('kyc/initiate/{customer}',                  [KycController::class, 'initiate'])->name('kyc.initiate');
        Route::post('kyc/{kyc}/upload-document',               [KycController::class, 'uploadDocument'])->name('kyc.upload-document');
        Route::post('kyc/{kyc}/review',                         [KycController::class, 'review'])->name('kyc.review');
        Route::post('kyc/{kyc}/reopen',                         [KycController::class, 'reopen'])->name('kyc.reopen');
        Route::get('kyc/documents/{document}',                  [KycController::class, 'serveDocument'])->name('kyc.document');

        // ── Notifications ─────────────────────────────────────────────────────
        Route::get('notifications',            [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all',  [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // ── Staff Profile ─────────────────────────────────────────────────────
        Route::get('profile',           [ProfileController::class, 'show'])->name('profile');
        Route::patch('profile',         [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

        // ── Active Sessions / Devices ────────────────────────────────────────
        Route::get('profile/sessions',              [DeviceSessionController::class, 'index'])->name('profile.sessions');
        Route::delete('profile/sessions/{session}', [DeviceSessionController::class, 'destroy'])->name('profile.sessions.destroy');
        Route::post('profile/sessions/logout-others', [DeviceSessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');
    });

// Logout fallback
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
