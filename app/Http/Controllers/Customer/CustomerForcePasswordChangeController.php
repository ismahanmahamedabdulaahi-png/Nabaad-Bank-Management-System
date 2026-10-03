<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CustomerForcePasswordChangeController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Customer/Auth/ForcePasswordChange');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $customer = Auth::guard('customer')->user();

        $customer->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        AuditLog::record('password.forced-change', 'customers', "Customer completed a required password change: {$customer->name} ({$customer->customer_number})");

        return redirect()->route('customer.dashboard')->with('success', 'Password updated. Welcome to NABAAD Bank.');
    }
}
