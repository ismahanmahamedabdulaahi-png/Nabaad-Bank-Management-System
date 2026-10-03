<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    protected $fillable = ['name', 'code', 'address', 'phone', 'email', 'manager_id', 'status'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function vault(): HasOne
    {
        return $this->hasOne(Vault::class);
    }

    public function endOfDayRecords(): HasMany
    {
        return $this->hasMany(EndOfDayRecord::class);
    }

    public function currentEod(): HasOne
    {
        return $this->hasOne(EndOfDayRecord::class)->latestOfMany('business_date');
    }
}
