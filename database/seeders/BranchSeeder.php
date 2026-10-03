<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Vault;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'GAR'],
            [
                'name'    => 'Garowe Branch',
                'address' => 'Garowe, Puntland, Somalia',
                'phone'   => '+252 5 842000',
                'email'   => 'garowe@nabaadbank.so',
                'status'  => 'active',
            ]
        );

        // Seed the vault for this branch (single row)
        Vault::firstOrCreate(
            ['branch_id' => $branch->id],
            [
                'balance'  => 0.00,
                'currency' => 'USD',
            ]
        );
    }
}
