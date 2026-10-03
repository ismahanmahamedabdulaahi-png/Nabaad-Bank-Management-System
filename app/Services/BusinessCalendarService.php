<?php

namespace App\Services;

use App\Models\PublicHoliday;
use Illuminate\Support\Carbon;

class BusinessCalendarService
{
    public function isWorkingDay(Carbon $date): bool
    {
        // Saturday (6) and Sunday (0) are non-working
        if ($date->isWeekend()) {
            return false;
        }

        return !PublicHoliday::isHoliday($date);
    }

    public function isPublicHoliday(Carbon $date): bool
    {
        return PublicHoliday::isHoliday($date);
    }

    public function nextWorkingDay(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay();
        while (!$this->isWorkingDay($next)) {
            $next->addDay();
        }
        return $next;
    }

    public function prevWorkingDay(Carbon $date): Carbon
    {
        $prev = $date->copy()->subDay();
        while (!$this->isWorkingDay($prev)) {
            $prev->subDay();
        }
        return $prev;
    }

    public function workingDaysInMonth(int $year, int $month): int
    {
        $count = 0;
        $date  = Carbon::create($year, $month, 1);
        while ($date->month === $month) {
            if ($this->isWorkingDay($date)) {
                $count++;
            }
            $date->addDay();
        }
        return $count;
    }

    // Roll a date forward to next working day if it falls on a weekend/holiday
    public function rollToNextWorkingDay(Carbon $date): Carbon
    {
        if ($this->isWorkingDay($date)) {
            return $date->copy();
        }
        return $this->nextWorkingDay($date);
    }
}
