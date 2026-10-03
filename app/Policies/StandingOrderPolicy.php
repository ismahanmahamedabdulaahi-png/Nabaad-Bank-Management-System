<?php

namespace App\Policies;

use App\Models\StandingOrder;
use App\Models\User;

class StandingOrderPolicy
{
    public function viewAny(User $user): bool  { return $user->hasPermissionTo('transactions.view'); }
    public function view(User $user, StandingOrder $order): bool { return $user->hasPermissionTo('transactions.view'); }
    public function create(User $user): bool   { return $user->hasPermissionTo('transactions.transfer'); }
    public function manage(User $user, StandingOrder $order): bool { return $user->hasPermissionTo('transactions.transfer'); }
}
