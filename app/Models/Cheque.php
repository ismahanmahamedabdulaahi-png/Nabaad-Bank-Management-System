<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    protected $fillable = [
        'cheque_book_id', 'account_id', 'cheque_number', 'payee_name', 'amount',
        'status', 'settlement_type', 'beneficiary_account_id',
        'issue_date', 'expiry_date', 'deposited_at', 'clearing_date',
        'cleared_at', 'bounce_reason', 'cancelled_at', 'cancelled_by',
        'processed_by', 'transaction_id', 'credit_transaction_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'issue_date'    => 'date',
            'expiry_date'   => 'date',
            'clearing_date' => 'date',
            'deposited_at'  => 'datetime',
            'cleared_at'    => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    public function chequeBook(): BelongsTo
    {
        return $this->belongsTo(ChequeBook::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function beneficiaryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'beneficiary_account_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'credit_transaction_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isIssued(): bool          { return $this->status === 'issued'; }
    public function isPaid(): bool            { return $this->status === 'paid'; }
    public function isDeposited(): bool       { return $this->status === 'deposited'; }
    public function isBounced(): bool         { return $this->status === 'bounced'; }
    public function isCancelled(): bool       { return $this->status === 'cancelled'; }
    public function isPendingClearance(): bool { return $this->status === 'pending_clearance'; }
    public function isCleared(): bool         { return $this->status === 'cleared'; }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['paid', 'deposited', 'bounced', 'cancelled', 'expired', 'cleared']);
    }
}
