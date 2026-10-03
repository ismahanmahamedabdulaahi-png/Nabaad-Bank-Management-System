<?php

namespace App\Policies;

use App\Models\ChequeBook;
use App\Models\User;

class ChequePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('cheques.view'); }
    public function view(User $user, ChequeBook $book): bool { return $user->hasPermissionTo('cheques.view'); }
    public function issue(User $user): bool   { return $user->hasPermissionTo('cheques.issue'); }
    public function verify(User $user): bool  { return $user->hasPermissionTo('cheques.verify'); }
    public function cash(User $user): bool    { return $user->hasPermissionTo('cheques.cash'); }
    public function cancel(User $user): bool  { return $user->hasPermissionTo('cheques.cancel'); }
}
