<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'account_number', 'customer_id', 'branch_id', 'account_type',
        'status', 'balance', 'interest_rate', 'minimum_balance', 'currency',
        'opening_date', 'last_activity_date', 'dormant_since',
        'fd_tenure_months', 'fd_maturity_date', 'fd_maturity_action',
        'opened_by', 'closed_by', 'frozen_by', 'frozen_at', 'closed_at', 'notes',
        'low_balance_alert_threshold', 'low_balance_alerted',
    ];

    protected function casts(): array
    {
        return [
            'balance'                     => 'decimal:2',
            'interest_rate'               => 'decimal:2',
            'minimum_balance'             => 'decimal:2',
            'low_balance_alert_threshold' => 'decimal:2',
            'low_balance_alerted'         => 'boolean',
            'opening_date'                => 'date',
            'last_activity_date'          => 'date',
            'dormant_since'               => 'date',
            'fd_maturity_date'            => 'date',
            'frozen_at'                   => 'datetime',
            'closed_at'                   => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function chequeBooks(): HasMany
    {
        return $this->hasMany(ChequeBook::class);
    }

    public function standingOrders(): HasMany
    {
        return $this->hasMany(StandingOrder::class, 'source_account_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isActive(): bool   { return $this->status === 'active'; }
    public function isFrozen(): bool   { return $this->status === 'frozen'; }
    public function isClosed(): bool   { return $this->status === 'closed'; }
    public function isDormant(): bool  { return $this->status === 'dormant'; }
    public function isMatured(): bool  { return $this->status === 'matured'; }
    // Deactivating a customer (inactive/blacklisted/deceased) never touched
    // their accounts' own status column — every deposit/withdraw/transfer/
    // cheque/loan/service-code check in this app relies on isOperational(),
    // so this is the one place that has to also look at the owner, not just
    // the account row, or a deactivated customer's money keeps moving freely.
    public function isOperational(): bool
    {
        return $this->status === 'active' && ($this->customer === null || $this->customer->isActive());
    }
    public function isFixedDeposit(): bool { return $this->account_type === 'fixed_deposit'; }

    public function getTypeLabel(): string
    {
        return match ($this->account_type) {
            'savings'       => 'Savings',
            'current'       => 'Current',
            'fixed_deposit' => 'Fixed Deposit',
            default         => ucfirst($this->account_type),
        };
    }

    public function getTypeAbbr(): string
    {
        return match ($this->account_type) {
            'savings'       => 'SA',
            'current'       => 'CA',
            'fixed_deposit' => 'FD',
            default         => 'XX',
        };
    }

    public function isMaturityDue(): bool
    {
        return $this->isFixedDeposit()
            && $this->fd_maturity_date
            && $this->fd_maturity_date->isPast()
            && !$this->isMatured();
    }
}
