<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EodRun extends Model
{
    protected $fillable = [
        'run_date', 'status', 'started_at', 'completed_at', 'triggered_by', 'is_manual',
        'standing_orders_success', 'standing_orders_failed', 'standing_orders_skipped',
        'cheques_cleared', 'loans_marked_overdue', 'dormant_accounts', 'matured_fds',
        'errors', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'run_date'      => 'date',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
            'is_manual'     => 'boolean',
            'errors'        => 'array',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isFailed(): bool    { return $this->status === 'failed'; }
    public function isRunning(): bool   { return $this->status === 'running'; }

    public function totalOperations(): int
    {
        return $this->standing_orders_success
             + $this->standing_orders_failed
             + $this->cheques_cleared
             + $this->loans_marked_overdue;
    }

    public function durationSeconds(): ?int
    {
        if (!$this->completed_at) return null;
        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
