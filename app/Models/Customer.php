<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guard = 'customer';

    protected $fillable = [
        'customer_number', 'name', 'email', 'phone', 'password', 'status', 'must_change_password', 'invitation_accepted_at',
        'gender', 'nationality', 'region', 'marital_status',
        'photo_path', 'signature_path', 'occupation', 'address', 'city',
        'date_of_birth', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship',
        'two_factor_enabled', 'two_factor_code', 'two_factor_expires_at',
        'last_login_at', 'last_login_ip', 'email_verified_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'two_factor_expires_at'  => 'datetime',
            'last_login_at'          => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'date_of_birth'          => 'date',
            'password'               => 'hashed',
            'two_factor_enabled'     => 'boolean',
            'must_change_password'   => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function activeAccounts(): HasMany
    {
        return $this->hasMany(Account::class)->where('status', 'active');
    }

    public function kyc(): HasOne
    {
        return $this->hasOne(KycVerification::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function chequeBooks(): HasMany
    {
        return $this->hasMany(ChequeBook::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isActive(): bool      { return $this->status === 'active'; }
    public function isPending(): bool     { return $this->status === 'pending'; }
    public function isBlacklisted(): bool { return $this->status === 'blacklisted'; }

    public function hasApprovedKyc(): bool
    {
        return $this->kyc?->isApproved() ?? false;
    }

    public function hasActiveLoan(): bool
    {
        return $this->loans()->whereIn('status', ['active', 'disbursed', 'overdue'])->exists();
    }
}
