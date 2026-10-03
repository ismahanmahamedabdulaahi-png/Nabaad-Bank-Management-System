<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessDay;
use App\Models\TellerTill;
use App\Models\Vault;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BusinessDayService
{
    public function __construct(private BusinessCalendarService $calendar) {}
    public function today(): ?BusinessDay
    {
        return BusinessDay::today();
    }

    public function isOpen(): bool
    {
        return BusinessDay::isOpenToday();
    }

    public function open(?string $notes): BusinessDay
    {
        // Block opening on public holidays
        if ($this->calendar->isPublicHoliday(today())) {
            $holidayName = \App\Models\PublicHoliday::where('date', today())
                ->orWhere(fn ($q) => $q->where('recurring_yearly', true)
                    ->whereMonth('date', today()->month)
                    ->whereDay('date', today()->day))
                ->value('name');
            throw ValidationException::withMessages([
                'business_date' => "Today is a public holiday" . ($holidayName ? " ({$holidayName})" : '') . ". Business day cannot be opened.",
            ]);
        }

        $existing = BusinessDay::where('business_date', today())->first();

        if ($existing && $existing->isOpen()) {
            throw ValidationException::withMessages([
                'business_date' => 'Business day for today is already open.',
            ]);
        }

        if ($existing && $existing->isClosed()) {
            throw ValidationException::withMessages([
                'business_date' => 'Today\'s business day has already been closed and cannot be reopened.',
            ]);
        }

        $day = BusinessDay::create([
            'business_date' => today(),
            'status'        => 'open',
            'opened_by'     => Auth::id(),
            'opened_at'     => now(),
            'notes'         => $notes,
        ]);

        AuditLog::record('business_day.open', 'business_days',
            'Business day opened for ' . today()->toFormattedDateString(), [], [
                'business_date' => today()->toDateString(),
            ]);

        return $day;
    }

    public function close(?string $notes): BusinessDay
    {
        $day = BusinessDay::where('business_date', today())->where('status', 'open')->first();

        if (!$day) {
            throw ValidationException::withMessages([
                'business_date' => 'No open business day found for today.',
            ]);
        }

        // All tills must be closed
        $openTills = TellerTill::where('business_date', today())->where('status', 'open')->count();
        if ($openTills > 0) {
            throw ValidationException::withMessages([
                'tills' => "Cannot close business day: {$openTills} till(s) are still open. Close all tills first.",
            ]);
        }

        // Vault must be closed
        $openVaults = Vault::where('status', 'open')->count();
        if ($openVaults > 0) {
            throw ValidationException::withMessages([
                'vault' => 'Cannot close business day: the vault is still open. Close the vault first.',
            ]);
        }

        $day->update([
            'status'    => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'notes'     => $notes ?? $day->notes,
        ]);

        AuditLog::record('business_day.close', 'business_days',
            'Business day closed for ' . today()->toFormattedDateString(), [], [
                'business_date' => today()->toDateString(),
            ]);

        return $day->fresh();
    }
}
