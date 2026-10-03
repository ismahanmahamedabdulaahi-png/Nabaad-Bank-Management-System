<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Root splash ────────────────────────────────────────────────────────────────
// Renders a branded loading screen that client-side redirects to the right
// destination, rather than issuing a server redirect straight away. Must still
// branch on auth state: the framework's default "guest" middleware sends an
// already-authenticated visitor of /login back to `/` when no `dashboard`/`home`
// route exists to redirect to instead — routing that case through a real 200
// response (instead of another redirect) is what keeps / <-> /login from
// looping (ERR_TOO_MANY_REDIRECTS) for anyone who is already logged in.
Route::get('/', function () {
    if (Auth::guard('web')->check()) {
        $redirectTo = route('admin.dashboard');
    } elseif (Auth::guard('customer')->check()) {
        $redirectTo = route('customer.dashboard');
    } else {
        $redirectTo = route('login');
    }

    return Inertia::render('Splash', ['redirect_to' => $redirectTo]);
});

// ── Staff (Admin) Routes ──────────────────────────────────────────────────────
require __DIR__.'/admin.php';

// ── Customer Portal Routes ────────────────────────────────────────────────────
require __DIR__.'/customer.php';
