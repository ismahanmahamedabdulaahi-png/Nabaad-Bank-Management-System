<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChequeBook extends Model
{
    protected $fillable = [
        'book_number', 'customer_id', 'account_id', 'series_start', 'series_end',
        'total_leaves', 'used_leaves', 'status', 'issued_by', 'issued_at',
        'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'    => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }
}
