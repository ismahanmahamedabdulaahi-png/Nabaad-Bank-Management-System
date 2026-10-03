<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TagSessionGuard
{
    // Laravel's DatabaseSessionHandler only ever stamps `user_id` from the
    // default guard (web/staff), so a customer-only session's row is left
    // with a NULL user_id. Re-stamping it here (with which guard it belongs
    // to) is what makes "list my active sessions" possible for either portal.
    //
    // This has to happen in terminate(), not handle(): the session row itself
    // is only written by StartSession's own terminate() step, which runs
    // after every middleware's handle() has already returned. Updating the
    // row inside handle() would race a not-yet-inserted row and silently
    // affect zero rows.
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $guard = null;
        $id    = null;

        if (Auth::guard('web')->check()) {
            $guard = 'web';
            $id    = Auth::guard('web')->id();
        } elseif (Auth::guard('customer')->check()) {
            $guard = 'customer';
            $id    = Auth::guard('customer')->id();
        }

        if ($guard) {
            DB::table('sessions')->where('id', $request->session()->getId())->update([
                'user_id' => $id,
                'guard'   => $guard,
            ]);
        }
    }
}
