<?php

namespace App\Policies;

use App\Models\KycVerification;
use App\Models\User;

class KycPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kyc.view');
    }

    public function view(User $user, KycVerification $kyc): bool
    {
        return $user->can('kyc.view');
    }

    public function approve(User $user, KycVerification $kyc): bool
    {
        return $user->can('kyc.approve');
    }

    public function reject(User $user, KycVerification $kyc): bool
    {
        return $user->can('kyc.reject');
    }

    public function upload(User $user, KycVerification $kyc): bool
    {
        return $user->can('kyc.view');
    }
}
