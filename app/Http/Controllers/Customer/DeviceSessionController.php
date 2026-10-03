<?php

namespace App\Http\Controllers\Customer;

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
        $customer = Auth::guard('customer')->user();

        return Inertia::render('Customer/Profile/Sessions', [
            'sessions' => $this->activeSessions('customer', $customer->id, $request),
        ]);
    }

    public function destroy(string $session): RedirectResponse
    {
        $this->destroySession('customer', Auth::guard('customer')->id(), $session);

        return back()->with('success', 'Device logged out.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required']);

        $customer = Auth::guard('customer')->user();

        if (!Hash::check($request->password, $customer->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        $this->destroyOtherSessions('customer', $customer->id, $request);

        return back()->with('success', 'All other devices have been logged out.');
    }
}
