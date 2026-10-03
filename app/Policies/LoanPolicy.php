<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool  { return $user->hasPermissionTo('loans.view'); }
    public function view(User $user, Loan $loan): bool { return $user->hasPermissionTo('loans.view'); }
    public function create(User $user): bool   { return $user->hasPermissionTo('loans.create'); }
    public function review(User $user, Loan $loan): bool  { return $user->hasPermissionTo('loans.review'); }
    public function approve(User $user, Loan $loan): bool { return $user->hasPermissionTo('loans.approve'); }
    public function disburse(User $user, Loan $loan): bool{ return $user->hasPermissionTo('loans.disburse'); }
    public function repay(User $user, Loan $loan): bool   { return $user->hasPermissionTo('loans.close'); }
    public function close(User $user, Loan $loan): bool   { return $user->hasPermissionTo('loans.close'); }
}
