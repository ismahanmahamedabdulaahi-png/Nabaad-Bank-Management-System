<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool  { return $user->hasPermissionTo('transactions.view'); }
    public function view(User $user, Transaction $txn): bool { return $user->hasPermissionTo('transactions.view'); }
    public function deposit(User $user): bool  { return $user->hasPermissionTo('transactions.deposit'); }
    public function withdraw(User $user): bool { return $user->hasPermissionTo('transactions.withdraw'); }
    public function transfer(User $user): bool { return $user->hasPermissionTo('transactions.transfer'); }
    public function reverse(User $user, Transaction $txn): bool { return $user->hasPermissionTo('transactions.reverse'); }
}
