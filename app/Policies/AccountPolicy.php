<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool  { return $user->hasPermissionTo('accounts.view'); }
    public function view(User $user, Account $account): bool { return $user->hasPermissionTo('accounts.view'); }
    public function create(User $user): bool   { return $user->hasPermissionTo('accounts.create'); }
    public function freeze(User $user, Account $account): bool   { return $user->hasPermissionTo('accounts.freeze'); }
    public function unfreeze(User $user, Account $account): bool { return $user->hasPermissionTo('accounts.freeze'); }
    public function close(User $user, Account $account): bool    { return $user->hasPermissionTo('accounts.close'); }
    public function reactivate(User $user, Account $account): bool { return $user->hasPermissionTo('accounts.reactivate'); }
}
