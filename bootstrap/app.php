<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Railway's HTTPS proxy — keeps asset/route URLs on https
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'auth.customer'     => \App\Http\Middleware\EnsureCustomerIsAuthenticated::class,
            'session.timeout'   => \App\Http\Middleware\SessionTimeout::class,
            'two-factor'        => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'customer-two-factor' => \App\Http\Middleware\EnsureCustomerTwoFactorVerified::class,
            'must-change-password' => \App\Http\Middleware\EnsureMustChangePassword::class,
            'customer-must-change-password' => \App\Http\Middleware\EnsureCustomerMustChangePassword::class,
            'customer-is-active' => \App\Http\Middleware\EnsureCustomerIsActive::class,
            'tag-session'       => \App\Http\Middleware\TagSessionGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->booted(function () {
        // Rate limiting: max 5 login attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('customer-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // 5 wrong 2FA codes per 3 minutes per user — a 6-digit code must not be brute-forceable
        // (3 minutes matches the code's own TTL — see SendTwoFactorCode/TwoFactorController)
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinutes(3, 5)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('customer-two-factor', function (Request $request) {
            return Limit::perMinutes(3, 5)->by(optional($request->user('customer'))->id ?: $request->ip());
        });
    })->create();
