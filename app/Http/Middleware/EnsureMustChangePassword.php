<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMustChangePassword
{
    // Unlike EnsureTwoFactorVerified's session flag (re-checked every login),
    // this reads a persistent column — a temporary/reset password must force a
    // change on every login attempt until the user actually sets their own,
    // not just for the current session.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if ($request->routeIs('admin.password.change*') || $request->routeIs('admin.logout')) {
                return $next($request);
            }

            return redirect()->route('admin.password.change');
        }

        return $next($request);
    }
}
