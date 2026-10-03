<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->can('users.view');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->can('users.view');
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('users.create');
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->can('users.edit');
    }

    public function delete(User $authUser, User $user): bool
    {
        // Cannot delete yourself or another Super Admin unless you are Super Admin
        if ($user->id === $authUser->id) {
            return false;
        }

        if ($user->hasRole('Super Admin') && !$authUser->hasRole('Super Admin')) {
            return false;
        }

        return $authUser->can('users.delete');
    }
}
