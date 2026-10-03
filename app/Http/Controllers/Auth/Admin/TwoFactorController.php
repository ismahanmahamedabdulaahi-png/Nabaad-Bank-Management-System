<?php

namespace App\Http\Controllers\Auth\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\SendTwoFactorCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/Auth/TwoFactor');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $key = 'two-factor|' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => "Too many attempts. Please wait {$seconds} seconds before trying again.",
            ]);
        }

        $user = Auth::user();

        if (
            $user->two_factor_code !== $request->code ||
            now()->isAfter($user->two_factor_expires_at)
        ) {
            RateLimiter::hit($key, 180); // 3-minute decay matches code TTL

            throw ValidationException::withMessages([
                'code' => 'Invalid or expired verification code.',
            ]);
        }

        RateLimiter::clear($key);
        $user->update(['two_factor_code' => null, 'two_factor_expires_at' => null]);
        session(['two_factor_verified' => true]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'two_factor_code'       => $code,
            'two_factor_expires_at' => now()->addMinutes(3),
        ]);

        $user->notify(new SendTwoFactorCode($code));

        RateLimiter::clear('two-factor|' . $user->id);

        return back()->with('status', 'A new verification code has been sent to your email.');
    }
}
