<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanRepaymentSchedule extends Model
{
    protected $fillable = [
        'loan_id', 'installment_number', 'due_date', 'grace_deadline',
        'principal_amount', 'interest_amount', 'emi_amount', 'penalty_amount',
        'total_due', 'amount_paid', 'outstanding_balance_after', 'status', 'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date'                 => 'date',
            'grace_deadline'           => 'date',
            'paid_date'                => 'date',
            'principal_amount'         => 'decimal:2',
            'interest_amount'          => 'decimal:2',
            'emi_amount'               => 'decimal:2',
            'penalty_amount'           => 'decimal:2',
            'total_due'                => 'decimal:2',
            'amount_paid'              => 'decimal:2',
            'outstanding_balance_after'=> 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'schedule_id');
    }

    public function isPaid(): bool    { return $this->status === 'paid'; }
    public function isOverdue(): bool { return $this->status === 'overdue'; }

    public function isWithinGrace(): bool
    {
        return now()->lte($this->grace_deadline);
    }
}
