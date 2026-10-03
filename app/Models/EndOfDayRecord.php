<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndOfDayRecord extends Model
{
    protected $fillable = [
        'branch_id', 'business_date', 'status', 'vault_opening_balance',
        'vault_closing_balance', 'total_transactions', 'total_deposits',
        'total_withdrawals', 'total_transfers', 'opened_by', 'opened_at',
        'closed_by', 'closed_at', 'reopened_by', 'reopened_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_date'          => 'date',
            'vault_opening_balance'  => 'decimal:2',
            'vault_closing_balance'  => 'decimal:2',
            'total_deposits'         => 'decimal:2',
            'total_withdrawals'      => 'decimal:2',
            'total_transfers'        => 'decimal:2',
            'opened_at'              => 'datetime',
            'closed_at'              => 'datetime',
            'reopened_at'            => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isClosed(): bool { return $this->status === 'closed'; }
}
