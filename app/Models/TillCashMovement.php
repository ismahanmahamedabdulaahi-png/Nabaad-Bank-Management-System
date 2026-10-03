<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillCashMovement extends Model
{
    protected $fillable = [
        'till_id', 'related_till_id', 'type', 'amount', 'notes', 'processed_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function till(): BelongsTo         { return $this->belongsTo(TellerTill::class); }
    public function relatedTill(): BelongsTo  { return $this->belongsTo(TellerTill::class, 'related_till_id'); }
    public function processedBy(): BelongsTo  { return $this->belongsTo(User::class, 'processed_by'); }

    public function isInflow(): bool { return in_array($this->type, ['replenishment', 'transfer_in']); }
    public function isOutflow(): bool{ return in_array($this->type, ['return', 'transfer_out']); }
}
