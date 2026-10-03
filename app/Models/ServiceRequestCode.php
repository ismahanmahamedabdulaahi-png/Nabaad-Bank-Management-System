<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestCode extends Model
{
    protected $fillable = [
        'customer_id', 'account_id', 'type', 'amount', 'code', 'status',
        'transaction_id', 'expires_at', 'redeemed_by', 'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'expires_at'  => 'datetime',
            'redeemed_at' => 'datetime',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isPast();
    }
}
