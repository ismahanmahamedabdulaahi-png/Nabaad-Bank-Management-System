<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    protected $fillable = [
        'loan_id', 'schedule_id', 'amount_paid', 'principal_paid',
        'interest_paid', 'penalty_paid', 'payment_date', 'transaction_id',
        'processed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid'    => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid'  => 'decimal:2',
            'penalty_paid'   => 'decimal:2',
            'payment_date'   => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanRepaymentSchedule::class, 'schedule_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
