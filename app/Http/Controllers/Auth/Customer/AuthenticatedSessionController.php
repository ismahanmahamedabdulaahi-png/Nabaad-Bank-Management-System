<?php

namespace App\Http\Controllers\Auth\Customer;

use App\Http\Controllers\Controller;
use App\Notifications\SendTwoFactorCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    // 5 wrong attempts locks the account out for 10 minutes.
    private const MAX_ATTEMPTS     = 5;
    private const LOCKOUT_SECONDS  = 600;

    public function create(): Response
    {
        return Inertia::render('Customer/Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'customer-login|'.Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->throwLockout($throttleKey);
        }

        if (!Auth::guard('customer')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            // Lock out immediately on the attempt that reaches the limit,
            // rather than waiting for one more submission to say so.
            $remaining = self::MAX_ATTEMPTS - RateLimiter::attempts($throttleKey);

            if ($remaining <= 0) {
                $this->throwLockout($throttleKey);
            }

            throw ValidationException::withMessages([
                'email' => "Incorrect email or password. {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before your account is locked for 10 minutes.",
            ]);
        }

        $customer = Auth::guard('customer')->user();
        if (!$customer->isActive()) {
            Auth::guard('customer')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account is not active. Please contact the bank.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        session(['last_activity_time' => time()]);

        $customer->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // 2FA is mandatory — generate a code, email it, and redirect to the verification page.
        if ($customer->two_factor_enabled) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $customer->update([
                'two_factor_code'       => $code,
                'two_factor_expires_at' => now()->addMinutes(3),
            ]);

            $customer->notify(new SendTwoFactorCode($code));

            return redirect()->route('customer.two-factor.show');
        }

        return redirect()->route('customer.dashboard');
    }

    private function throwLockout(string $throttleKey): void
    {
        $seconds = RateLimiter::availableIn($throttleKey);
        $minutes = (int) ceil($seconds / 60);

        throw ValidationException::withMessages([
            'email' => "Locked — too many failed attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
