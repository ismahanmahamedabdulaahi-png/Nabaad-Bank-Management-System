<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandingOrder extends Model
{
    protected $fillable = [
        'source_account_id', 'beneficiary_account_id', 'amount', 'frequency',
        'start_date', 'next_execution_date', 'end_date', 'description', 'status',
        'executions_count', 'last_executed_at', 'last_execution_status',
        'created_by_user', 'created_by_customer',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'start_date'           => 'date',
            'next_execution_date'  => 'date',
            'end_date'             => 'date',
            'last_executed_at'     => 'datetime',
        ];
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function beneficiaryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'beneficiary_account_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function createdByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'created_by_customer');
    }

    public function isActive(): bool { return $this->status === 'active'; }
}
