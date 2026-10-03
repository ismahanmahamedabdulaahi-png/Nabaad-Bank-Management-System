<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlAccount extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'normal_balance', 'description', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'debit';
    }

    // Signed balance in the account's own normal-balance direction (debit total - credit
    // total for debit-normal accounts like assets/expenses; the reverse for liabilities,
    // equity, and income).
    public function balance(?string $asOf = null): float
    {
        $query = $this->lines();
        if ($asOf) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf));
        }

        $totals = $query->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')->first();

        return $this->isDebitNormal()
            ? (float) $totals->debit_total - (float) $totals->credit_total
            : (float) $totals->credit_total - (float) $totals->debit_total;
    }
}
