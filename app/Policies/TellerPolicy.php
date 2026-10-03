<?php

namespace App\Policies;

use App\Models\TellerTill;
use App\Models\User;

class TellerPolicy
{
    public function viewAny(User $user): bool  { return $user->hasPermissionTo('teller.open-till') || $user->hasPermissionTo('teller.close-till'); }
    public function view(User $user, TellerTill $till): bool { return $this->viewAny($user); }
    public function create(User $user): bool   { return $user->hasPermissionTo('teller.assign'); }
    public function close(User $user, TellerTill $till): bool { return $user->hasPermissionTo('teller.close-till'); }
    public function transfer(User $user): bool { return $user->hasPermissionTo('teller.transfer'); }
    public function reconcile(User $user, TellerTill $till): bool { return $user->hasPermissionTo('teller.reconcile'); }
}
