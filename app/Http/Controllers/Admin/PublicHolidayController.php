<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicHolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:public-holidays.manage')->only(['store', 'destroy']);
    }

    public function index(Request $request): Response
    {
        $this->authorize('eod.run');

        $year = (int) ($request->year ?? now()->year);

        $holidays = PublicHoliday::orderBy('date')->get();

        $effective = $holidays->map(function ($h) use ($year) {
            $date = $h->recurring_yearly
                ? \Illuminate\Support\Carbon::create($year, $h->date->month, $h->date->day)
                : $h->date->copy();

            return [
                'id'               => $h->id,
                'name'             => $h->name,
                'date'             => $h->date->toDateString(),
                'effective_date'   => $date->toDateString(),
                'description'      => $h->description,
                'recurring_yearly' => $h->recurring_yearly,
                'day_name'         => $date->format('l'),
            ];
        });

        return Inertia::render('Admin/PublicHolidays/Index', [
            'holidays'            => $effective,
            'year'                => $year,
            'canManageHolidays'   => $request->user()->can('public-holidays.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:191',
            'date'              => 'required|date|unique:public_holidays,date',
            'description'       => 'nullable|string|max:500',
            'recurring_yearly'  => 'boolean',
        ]);

        PublicHoliday::create($data);

        return back()->with('success', "Holiday '{$data['name']}' added for {$data['date']}.");
    }

    public function destroy(PublicHoliday $holiday): RedirectResponse
    {
        $name = $holiday->name;
        $holiday->delete();

        return back()->with('success', "Holiday '{$name}' removed.");
    }
}
