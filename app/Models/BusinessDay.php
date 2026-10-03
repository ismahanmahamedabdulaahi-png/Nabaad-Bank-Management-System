<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDay extends Model
{
    protected $fillable = [
        'business_date', 'status', 'opened_by', 'opened_at',
        'closed_by', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at'     => 'datetime',
            'closed_at'     => 'datetime',
        ];
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

    public static function today(): ?self
    {
        return static::where('business_date', today())->first();
    }

    public static function isOpenToday(): bool
    {
        return static::where('business_date', today())->where('status', 'open')->exists();
    }
}
