<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Notifications\CustomerOtpIssued;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvitationController extends Controller
{
    // GET/POST both hit this same signed URL (see routes/customer.php) — the
    // `signed` middleware rejects a tampered or expired link before either
    // action runs. Mirrors Admin\StaffInvitationController for staff.
    public function show(Request $request, Customer $customer): Response
    {
        return Inertia::render('Customer/Auth/AcceptInvitation', [
            'name'         => $customer->name,
            'already_done' => $customer->invitation_accepted_at !== null,
            'accept_url'   => $request->fullUrl(),
        ]);
    }

    public function accept(Request $request, Customer $customer): Response
    {
        if ($customer->invitation_accepted_at !== null) {
            return Inertia::render('Customer/Auth/AcceptInvitation', [
                'name'         => $customer->name,
                'already_done' => true,
                'accept_url'   => $request->fullUrl(),
            ]);
        }

        $otp = Str::password(12);

        $customer->update([
            'invitation_accepted_at' => now(),
            'password'               => Hash::make($otp),
            'must_change_password'   => true,
        ]);

        $customer->notify(new CustomerOtpIssued($otp));

        AuditLog::record('invitation.accepted', 'customers', "Customer invitation accepted: {$customer->name} ({$customer->customer_number})", [], [
            'customer_id' => $customer->id,
        ]);

        return Inertia::render('Customer/Auth/InvitationAccepted', [
            'name' => $customer->name,
        ]);
    }
}
