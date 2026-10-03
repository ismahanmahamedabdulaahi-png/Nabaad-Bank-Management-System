<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerMustChangePassword
{
    // Customer-guard mirror of EnsureMustChangePassword — see that class for
    // why this reads a persistent column rather than a session flag.
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->must_change_password) {
            if ($request->routeIs('customer.password.change*') || $request->routeIs('customer.logout')) {
                return $next($request);
            }

            return redirect()->route('customer.password.change');
        }

        return $next($request);
    }
}
