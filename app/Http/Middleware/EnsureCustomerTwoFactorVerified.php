<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->two_factor_enabled && !session('customer_two_factor_verified')) {
            // Already heading to 2FA routes or logout — don't loop
            if ($request->routeIs('customer.two-factor.*') || $request->routeIs('customer.logout')) {
                return $next($request);
            }

            return redirect()->route('customer.two-factor.show');
        }

        return $next($request);
    }
}
