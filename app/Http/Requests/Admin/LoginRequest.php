<?php

namespace App\Http\Requests\Admin;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    // 5 wrong attempts locks the account out for 10 minutes.
    private const MAX_ATTEMPTS  = 5;
    private const LOCKOUT_SECONDS = 600;

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'staff_id' => Str::upper($this->string('staff_id')),
            'password' => $this->string('password'),
        ];

        if (!Auth::guard('web')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);

            // Lock out immediately on the attempt that reaches the limit,
            // rather than showing one more generic "failed" message and only
            // locking on the *next* submission.
            $remaining = self::MAX_ATTEMPTS - RateLimiter::attempts($this->throttleKey());

            if ($remaining <= 0) {
                $this->throwLockout();
            }

            throw ValidationException::withMessages([
                'staff_id' => "Incorrect Staff ID or password. {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before your account is locked for 10 minutes.",
            ]);
        }

        // Ensure the staff account is active
        $user = Auth::guard('web')->user();
        if (!$user->isActive()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'staff_id' => 'Your account has been suspended. Please contact the administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $this->throwLockout();
    }

    private function throwLockout(): void
    {
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $minutes = (int) ceil($seconds / 60);

        throw ValidationException::withMessages([
            'staff_id' => "Locked — too many failed attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.',
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('staff_id')).'|'.$this->ip());
    }
}
