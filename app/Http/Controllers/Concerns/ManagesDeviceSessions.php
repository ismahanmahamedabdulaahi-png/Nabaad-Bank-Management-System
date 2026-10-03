<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait ManagesDeviceSessions
{
    protected function activeSessions(string $guard, int $userId, Request $request): array
    {
        $currentId = $request->session()->getId();

        return DB::table('sessions')
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($row) use ($currentId) {
                $device = $this->parseUserAgent($row->user_agent);

                return [
                    'id'            => $row->id,
                    'ip_address'    => $row->ip_address,
                    'browser'       => $device['browser'],
                    'platform'      => $device['platform'],
                    'last_active'   => date('c', $row->last_activity),
                    'is_current'    => $row->id === $currentId,
                ];
            })
            ->values()
            ->all();
    }

    protected function destroySession(string $guard, int $userId, string $sessionId): void
    {
        DB::table('sessions')
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete();
    }

    protected function destroyOtherSessions(string $guard, int $userId, Request $request): void
    {
        $currentId = $request->session()->getId();

        DB::table('sessions')
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->where('id', '!=', $currentId)
            ->delete();
    }

    // No dependency added for this — a small, deliberately approximate
    // regex match on the handful of OS/browser tokens actually seen in
    // practice is enough for a "which device is this" display.
    private function parseUserAgent(?string $ua): array
    {
        $ua = $ua ?? '';

        $platform = match (true) {
            (bool) preg_match('/windows/i', $ua) => 'Windows',
            (bool) preg_match('/iphone|ipad/i', $ua) => 'iOS',
            (bool) preg_match('/android/i', $ua) => 'Android',
            (bool) preg_match('/macintosh|mac os/i', $ua) => 'macOS',
            (bool) preg_match('/linux/i', $ua) => 'Linux',
            default => 'Unknown device',
        };

        $browser = match (true) {
            (bool) preg_match('/edg\//i', $ua) => 'Edge',
            (bool) preg_match('/chrome|crios/i', $ua) => 'Chrome',
            (bool) preg_match('/firefox/i', $ua) => 'Firefox',
            (bool) preg_match('/safari/i', $ua) => 'Safari',
            default => 'Unknown browser',
        };

        return ['platform' => $platform, 'browser' => $browser];
    }
}
