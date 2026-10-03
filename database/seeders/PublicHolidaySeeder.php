<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['name' => 'New Year\'s Day',          'date' => '2026-01-01', 'recurring_yearly' => true,  'description' => 'International New Year'],
            ['name' => 'Labour Day',                'date' => '2026-05-01', 'recurring_yearly' => true,  'description' => 'International Labour Day'],
            ['name' => 'Independence Day',          'date' => '2026-07-01', 'recurring_yearly' => true,  'description' => 'Somalia Independence Day'],
            ['name' => 'Foundation Day',            'date' => '2026-10-21', 'recurring_yearly' => true,  'description' => 'Somali Foundation Day'],
            ['name' => 'Eid Al-Adha',               'date' => '2026-06-07', 'recurring_yearly' => false, 'description' => 'Islamic Eid Al-Adha'],
            ['name' => 'Eid Al-Fitr',               'date' => '2026-03-31', 'recurring_yearly' => false, 'description' => 'Islamic Eid Al-Fitr'],
        ];

        foreach ($holidays as $holiday) {
            DB::table('public_holidays')->updateOrInsert(
                ['date' => $holiday['date']],
                array_merge($holiday, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
