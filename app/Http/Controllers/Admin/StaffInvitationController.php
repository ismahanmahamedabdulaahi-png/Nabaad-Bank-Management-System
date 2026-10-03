<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffOtpIssued;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StaffInvitationController extends Controller
{
    // GET/POST both hit this same signed URL (see routes/admin.php) — the
    // `signed` middleware rejects a tampered or expired link before either
    // action runs, so no separate token column is needed on `users`.
    public function show(Request $request, User $user): Response
    {
        return Inertia::render('Admin/Auth/AcceptInvitation', [
            'name'          => $user->name,
            'role'          => $user->roles->first()?->name,
            'already_done'  => !$user->isInvited() || $user->invitation_accepted_at !== null,
            'accept_url'    => $request->fullUrl(),
        ]);
    }

    public function accept(Request $request, User $user): RedirectResponse|Response
    {
        if (!$user->isInvited() || $user->invitation_accepted_at !== null) {
            return Inertia::render('Admin/Auth/AcceptInvitation', [
                'name'         => $user->name,
                'role'         => $user->roles->first()?->name,
                'already_done' => true,
                'accept_url'   => $request->fullUrl(),
            ]);
        }

        $otp = Str::password(12);

        $user->update([
            'status'                 => 'active',
            'invitation_accepted_at' => now(),
            'password'               => Hash::make($otp),
            'must_change_password'   => true,
        ]);

        $user->notify(new StaffOtpIssued($user->staff_id, $otp));

        AuditLog::record('invitation.accepted', 'users', "Staff invitation accepted: {$user->name} ({$user->staff_id})", [], [
            'user_id' => $user->id,
        ]);

        return Inertia::render('Admin/Auth/InvitationAccepted', [
            'name' => $user->name,
        ]);
    }
}
