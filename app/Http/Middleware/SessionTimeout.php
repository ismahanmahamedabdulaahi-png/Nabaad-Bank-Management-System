<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        if (Auth::guard($guard)->check()) {
            $timeoutMinutes = (int) config('session.lifetime', 10);
            $lastActivity   = session('last_activity_time');

            if ($lastActivity && (time() - $lastActivity) > ($timeoutMinutes * 60)) {
                Auth::guard($guard)->logout();
                session()->invalidate();
                session()->regenerateToken();

                $loginRoute = $guard === 'customer' ? 'customer.login' : 'login';

                return redirect()->route($loginRoute)
                    ->with('warning', 'Your session expired due to inactivity. Please log in again.');
            }

            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}
