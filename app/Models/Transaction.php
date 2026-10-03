<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference', 'account_id', 'related_account_id', 'type', 'amount',
        'balance_before', 'balance_after', 'status', 'description', 'notes', 'currency',
        'requires_approval', 'approval_level_required', 'approval_level_reached', 'escalated_at',
        'teller_till_id', 'processed_by', 'initiated_by_customer_id', 'reversal_of', 'reversal_reason',
        'receipt_path', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'balance_before'   => 'decimal:2',
            'balance_after'    => 'decimal:2',
            'requires_approval'=> 'boolean',
            'completed_at'     => 'datetime',
            'escalated_at'     => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function relatedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'related_account_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function tellerTill(): BelongsTo
    {
        return $this->belongsTo(TellerTill::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TransactionApproval::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversal_of');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }
    public function isApproved(): bool   { return $this->status === 'approved'; }
    public function needsApproval(): bool { return $this->requires_approval && $this->status === 'pending'; }

    public function isStale(): bool
    {
        return $this->needsApproval() && $this->created_at->lt(now()->subHours(24));
    }
}
