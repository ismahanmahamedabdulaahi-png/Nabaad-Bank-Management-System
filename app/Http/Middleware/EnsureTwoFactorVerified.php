<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_enabled && !session('two_factor_verified')) {
            // Already heading to 2FA routes or logout — don't loop
            if ($request->routeIs('two-factor.*') || $request->routeIs('admin.logout')) {
                return $next($request);
            }

            return redirect()->route('two-factor.show');
        }

        return $next($request);
    }
}
