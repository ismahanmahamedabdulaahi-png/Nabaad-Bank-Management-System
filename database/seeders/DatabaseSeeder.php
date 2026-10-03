<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Phase 1
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            // Phase 2
            BranchSeeder::class,
            SettingsSeeder::class,
            PublicHolidaySeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }
}
