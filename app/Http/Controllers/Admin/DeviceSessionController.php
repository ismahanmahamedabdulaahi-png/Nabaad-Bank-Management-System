<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesDeviceSessions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class DeviceSessionController extends Controller
{
    use ManagesDeviceSessions;

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return Inertia::render('Admin/Profile/Sessions', [
            'sessions' => $this->activeSessions('web', $user->id, $request),
        ]);
    }

    public function destroy(string $session): RedirectResponse
    {
        $this->destroySession('web', Auth::id(), $session);

        return back()->with('success', 'Device logged out.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required']);

        if (!Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        $this->destroyOtherSessions('web', Auth::id(), $request);

        return back()->with('success', 'All other devices have been logged out.');
    }
}
