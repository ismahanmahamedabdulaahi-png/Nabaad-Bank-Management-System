<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PublicHoliday extends Model
{
    protected $fillable = ['name', 'date', 'description', 'recurring_yearly'];

    protected function casts(): array
    {
        return [
            'date'              => 'date',
            'recurring_yearly'  => 'boolean',
        ];
    }

    // Check if a given date is a public holiday (handles recurring_yearly)
    public static function isHoliday(Carbon $date): bool
    {
        return static::where(function ($q) use ($date) {
            // Exact date match
            $q->where('date', $date->toDateString())
              // OR recurring: same month+day in any year
              ->orWhere(function ($q2) use ($date) {
                  $q2->where('recurring_yearly', true)
                     ->whereMonth('date', $date->month)
                     ->whereDay('date', $date->day);
              });
        })->exists();
    }

    // All holidays that apply to a given year (for calendar display)
    public static function forYear(int $year): \Illuminate\Database\Eloquent\Collection
    {
        return static::where(function ($q) use ($year) {
            $q->whereYear('date', $year)
              ->orWhere('recurring_yearly', true);
        })->orderBy('date')->get();
    }
}
