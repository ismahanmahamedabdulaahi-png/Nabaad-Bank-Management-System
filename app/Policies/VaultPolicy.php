<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vault;

class VaultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('vault.view');
    }

    public function view(User $user, Vault $vault): bool
    {
        return $user->hasPermissionTo('vault.view');
    }

    public function cashIn(User $user, Vault $vault): bool
    {
        return $user->hasPermissionTo('vault.cash-in');
    }

    public function cashOut(User $user, Vault $vault): bool
    {
        return $user->hasPermissionTo('vault.cash-out');
    }

    public function transfer(User $user, Vault $vault): bool
    {
        return $user->hasPermissionTo('vault.transfer');
    }
}
