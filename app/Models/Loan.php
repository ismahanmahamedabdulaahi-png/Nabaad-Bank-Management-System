<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'loan_number', 'customer_id', 'account_id', 'amount', 'interest_rate',
        'tenure_months', 'monthly_emi', 'total_payable', 'total_interest',
        'outstanding_balance', 'total_paid', 'purpose', 'collateral', 'status',
        'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
        'disbursed_by', 'disbursed_at', 'closed_at', 'rejection_reason',
        'disbursement_transaction_id', 'account_age_months', 'prev_year_transaction_volume',
        'grace_period_days', 'penalty_rate', 'first_repayment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount'                       => 'decimal:2',
            'interest_rate'                => 'decimal:2',
            'monthly_emi'                  => 'decimal:2',
            'total_payable'                => 'decimal:2',
            'total_interest'               => 'decimal:2',
            'outstanding_balance'          => 'decimal:2',
            'total_paid'                   => 'decimal:2',
            'prev_year_transaction_volume' => 'decimal:2',
            'penalty_rate'                 => 'decimal:2',
            'reviewed_at'                  => 'datetime',
            'approved_at'                  => 'datetime',
            'disbursed_at'                 => 'datetime',
            'closed_at'                    => 'datetime',
            'first_repayment_date'         => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(LoanRepaymentSchedule::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class)->orderBy('payment_date');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function isActive(): bool   { return $this->status === 'active'; }
    public function isOverdue(): bool  { return $this->status === 'overdue'; }
    public function isClosed(): bool   { return $this->status === 'closed'; }
}
