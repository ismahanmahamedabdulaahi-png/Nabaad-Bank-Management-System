<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $fillable = [
        'customer_id', 'account_id', 'name', 'limit_amount',
        'period', 'threshold_percent', 'last_alert_percent', 'last_alert_period_start', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'limit_amount'            => 'decimal:2',
            'threshold_percent'       => 'integer',
            'last_alert_percent'      => 'integer',
            'last_alert_period_start' => 'date',
            'is_active'               => 'boolean',
        ];
    }

    /**
     * Alert progress resets every time a new period starts, even though the
     * `last_alert_percent` column itself isn't cleared — this treats a stale
     * alert (from a prior period) as if no alert had been sent yet.
     */
    public function currentPeriodAlertPercent(): int
    {
        if (!$this->last_alert_period_start || !$this->last_alert_period_start->isSameDay($this->periodStart())) {
            return 0;
        }
        return $this->last_alert_percent;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // ── Period helpers ────────────────────────────────────────────────────────

    public function periodStart(): \Carbon\Carbon
    {
        return match ($this->period) {
            'monthly' => now()->startOfMonth(),
            default   => now()->startOfMonth(),
        };
    }

    /**
     * Total spent (withdrawals, outgoing transfers, loan repayments) against
     * this budget's scope (one account, or all of the customer's accounts)
     * within the current period.
     */
    public function spentThisPeriod(): float
    {
        $accountIds = $this->account_id
            ? [$this->account_id]
            : Account::where('customer_id', $this->customer_id)->pluck('id');

        return (float) Transaction::whereIn('account_id', $accountIds)
            ->whereIn('type', ['withdrawal', 'transfer', 'loan_repayment'])
            ->where('status', 'completed')
            ->where('created_at', '>=', $this->periodStart())
            ->sum('amount');
    }

    public function percentUsed(): float
    {
        if ((float) $this->limit_amount <= 0) return 0;
        return round(($this->spentThisPeriod() / (float) $this->limit_amount) * 100, 1);
    }

    public function isExceeded(): bool
    {
        return $this->spentThisPeriod() >= (float) $this->limit_amount;
    }
}
