<?php

use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\BudgetController;
use App\Http\Controllers\Customer\ChequeController;
use App\Http\Controllers\Customer\ComplaintController;
use App\Http\Controllers\Customer\CustomerForcePasswordChangeController;
use App\Http\Controllers\Customer\CustomerInvitationController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\DeviceSessionController;
use App\Http\Controllers\Customer\LoanController;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\ServiceCodeController;
use App\Http\Controllers\Customer\StandingOrderController;
use App\Http\Controllers\Customer\TransactionController;
use App\Http\Controllers\Customer\TransferController;
use App\Http\Controllers\Auth\Customer\AuthenticatedSessionController as CustomerSessionController;
use App\Http\Controllers\Auth\Customer\PasswordResetLinkController    as CustomerPasswordResetController;
use App\Http\Controllers\Auth\Customer\NewPasswordController          as CustomerNewPasswordController;
use App\Http\Controllers\Auth\Customer\RegisterController             as CustomerRegisterController;
use App\Http\Controllers\Auth\Customer\TwoFactorController             as CustomerTwoFactorController;
use Illuminate\Support\Facades\Route;

// ── Customer Invitation (unauthenticated, protected by the signed link itself) ─
Route::get('/portal/invitations/{customer}/accept', [CustomerInvitationController::class, 'show'])
    ->middleware('signed')
    ->name('customer-invitation.show');
Route::post('/portal/invitations/{customer}/accept', [CustomerInvitationController::class, 'accept'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('customer-invitation.accept');

// ── Customer Authentication (unauthenticated) ─────────────────────────────────
Route::middleware('guest:customer')
    ->prefix('portal')
    ->name('customer.')
    ->group(function () {
        Route::get('/login',          [CustomerSessionController::class,       'create'])->name('login');
        Route::post('/login',         [CustomerSessionController::class,       'store'])->middleware('throttle:customer-login');

        Route::get('/register',       [CustomerRegisterController::class, 'create'])->name('register');
        Route::post('/register',      [CustomerRegisterController::class, 'store']);

        Route::get('/forgot-password', [CustomerPasswordResetController::class, 'create'])->name('password.request');
        Route::post('/forgot-password',[CustomerPasswordResetController::class, 'store'])->name('password.email');

        Route::get('/reset-password/{token}', [CustomerNewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password',        [CustomerNewPasswordController::class, 'store'])->name('password.store');
    });

// ── Customer 2FA verification ─────────────────────────────────────────────────
Route::middleware('auth.customer')
    ->prefix('portal')
    ->name('customer.')
    ->group(function () {
        Route::get('/two-factor',  [CustomerTwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/two-factor', [CustomerTwoFactorController::class, 'verify'])
            ->middleware('throttle:customer-two-factor')
            ->name('two-factor.verify');
        Route::post('/two-factor/resend', [CustomerTwoFactorController::class, 'resend'])->name('two-factor.resend');
    });

// ── Forced password change — reachable once past 2FA but deliberately outside
// the main portal group below, since that group requires passing this very gate ─
Route::middleware(['auth.customer', 'customer-is-active', 'customer-two-factor'])
    ->prefix('portal')
    ->name('customer.')
    ->group(function () {
        Route::get('/password/change', [CustomerForcePasswordChangeController::class, 'show'])->name('password.change');
        Route::post('/password/change', [CustomerForcePasswordChangeController::class, 'update'])->name('password.change.store');
    });

// ── Customer Authenticated Area ───────────────────────────────────────────────
Route::middleware(['auth.customer', 'customer-is-active', 'customer-two-factor', 'customer-must-change-password', 'session.timeout:customer', 'tag-session'])
    ->prefix('portal')
    ->name('customer.')
    ->group(function () {
        Route::post('/logout', [CustomerSessionController::class, 'destroy'])->name('logout');

        // Session keep-alive — polled while a long form is open, so the session doesn't expire mid-fill.
        Route::get('/ping', fn () => response()->noContent())->name('ping');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Accounts + Statement
        Route::get('/accounts',                          [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/{account}',                [AccountController::class, 'show'])->name('accounts.show');
        Route::get('/accounts/{account}/statement',      [AccountController::class, 'statement'])->name('accounts.statement');
        Route::patch('/accounts/{account}/alert-threshold', [AccountController::class, 'updateAlertThreshold'])->name('accounts.alert-threshold');

        // Budgets
        Route::get('/budgets',                [BudgetController::class, 'index'])->name('budgets.index');
        Route::get('/budgets/create',          [BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budgets',                [BudgetController::class, 'store'])->name('budgets.store');
        Route::post('/budgets/{budget}/toggle',[BudgetController::class, 'toggle'])->name('budgets.toggle');
        Route::delete('/budgets/{budget}',     [BudgetController::class, 'destroy'])->name('budgets.destroy');

        // Notifications
        Route::get('/notifications',                 [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read',       [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all',        [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // Transactions
        Route::get('/transactions',               [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::get('/transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');

        // Instant Transfer
        Route::get('/transfer',  [TransferController::class, 'create'])->name('transfer.create');
        Route::post('/transfer', [TransferController::class, 'store'])->name('transfer.store');

        // Service Codes (cardless withdrawal / deposit pre-register)
        Route::get('/service-codes',                    [ServiceCodeController::class, 'index'])->name('service-codes.index');
        Route::post('/service-codes/withdrawal',        [ServiceCodeController::class, 'requestWithdrawal'])->name('service-codes.request-withdrawal');
        Route::post('/service-codes/deposit',           [ServiceCodeController::class, 'requestDeposit'])->name('service-codes.request-deposit');

        // Complaints
        Route::get('/complaints',            [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/new',        [ComplaintController::class, 'create'])->name('complaints.create');
        Route::post('/complaints',           [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');

        // Loans
        Route::get('/loans',               [LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/apply',         [LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans',              [LoanController::class, 'store'])->name('loans.store');
        Route::get('/loans/{loan}',        [LoanController::class, 'show'])->name('loans.show');
        Route::post('/loans/{loan}/repay', [LoanController::class, 'repay'])->name('loans.repay');

        // Standing Orders
        Route::get('/standing-orders',                      [StandingOrderController::class, 'index'])->name('standing-orders.index');
        Route::get('/standing-orders/create',               [StandingOrderController::class, 'create'])->name('standing-orders.create');
        Route::post('/standing-orders',                     [StandingOrderController::class, 'store'])->name('standing-orders.store');
        Route::post('/standing-orders/{order}/pause',       [StandingOrderController::class, 'pause'])->name('standing-orders.pause');
        Route::post('/standing-orders/{order}/resume',      [StandingOrderController::class, 'resume'])->name('standing-orders.resume');
        Route::post('/standing-orders/{order}/cancel',      [StandingOrderController::class, 'cancel'])->name('standing-orders.cancel');

        // Cheques
        Route::get('/cheques', [ChequeController::class, 'index'])->name('cheques.index');

        // Profile + Password
        Route::get('/profile',                [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile',              [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password',      [ProfileController::class, 'changePassword'])->name('profile.password');

        // Active Sessions / Devices
        Route::get('/profile/sessions',              [DeviceSessionController::class, 'index'])->name('profile.sessions');
        Route::delete('/profile/sessions/{session}', [DeviceSessionController::class, 'destroy'])->name('profile.sessions.destroy');
        Route::post('/profile/sessions/logout-others', [DeviceSessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');
    });
