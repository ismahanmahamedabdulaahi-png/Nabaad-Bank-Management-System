<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'nabaadbank@gmail.com'],
            [
                'staff_id'          => 'STF-0001',
                'name'              => 'Super Admin',
                'password'          => Hash::make('Admin@1234'),
                'status'            => 'active',
                'transaction_limit' => 999999999.99,
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('Super Admin');
    }
}
