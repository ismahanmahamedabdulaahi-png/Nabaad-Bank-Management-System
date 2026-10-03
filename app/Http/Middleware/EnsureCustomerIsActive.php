<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    // Login already refuses an inactive/blacklisted/deceased customer, but
    // that check only runs once, at login — a customer already mid-session
    // when staff deactivates them keeps a fully working session otherwise.
    // This re-checks status on every request in the authenticated group and
    // kicks them out the moment it stops being 'active'.
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && !$customer->isActive()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('customer.login')
                ->with('warning', 'Your account is no longer active. Please contact the bank.');
        }

        return $next($request);
    }
}
