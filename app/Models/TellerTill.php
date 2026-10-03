<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TellerTill extends Model
{
    protected $fillable = [
        'till_name', 'teller_id', 'assigned_by', 'status', 'opening_balance',
        'current_balance', 'closing_balance', 'expected_balance', 'variance',
        'business_date', 'opened_at', 'opened_by', 'closed_at', 'closed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance'  => 'decimal:2',
            'current_balance'  => 'decimal:2',
            'closing_balance'  => 'decimal:2',
            'expected_balance' => 'decimal:2',
            'variance'         => 'decimal:2',
            'business_date'    => 'date',
            'opened_at'        => 'datetime',
            'closed_at'        => 'datetime',
        ];
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(TillCashMovement::class, 'till_id');
    }

    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    public function expectedBalance(): float
    {
        $inflows  = $this->cashMovements()
            ->whereIn('type', ['replenishment', 'transfer_in', 'customer_deposit'])
            ->sum('amount');
        $outflows = $this->cashMovements()
            ->whereIn('type', ['return', 'transfer_out', 'customer_withdrawal'])
            ->sum('amount');
        return (float) $this->opening_balance + (float) $inflows - (float) $outflows;
    }
}
