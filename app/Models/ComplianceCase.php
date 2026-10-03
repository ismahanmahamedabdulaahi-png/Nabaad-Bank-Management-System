<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceCase extends Model
{
    protected $fillable = [
        'customer_id', 'transaction_id', 'type', 'severity', 'status', 'notes', 'flagged_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function isSystemGenerated(): bool
    {
        return $this->flagged_by === null;
    }
}
