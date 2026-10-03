<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class AccountNumberService
{
    // Format: {3-letter branch code}{1020}{5-digit sequence}
    // Example: GAR102000001

    public function generate(Branch $branch): string
    {
        return DB::transaction(function () use ($branch) {
            // Atomic increment — lock the settings row to prevent race conditions
            $setting = DB::table('settings')
                ->where('key', 'account_number_sequence')
                ->lockForUpdate()
                ->first();

            $current = (int) ($setting->value ?? 0);
            $next    = $current + 1;

            DB::table('settings')
                ->where('key', 'account_number_sequence')
                ->update(['value' => $next]);

            $prefix   = strtoupper(substr($branch->code, 0, 3));  // GAR
            $sequence = str_pad($next, 5, '0', STR_PAD_LEFT);     // 00001

            return $prefix . '1020' . $sequence;                   // GAR102000001
        });
    }

    public function preview(Branch $branch, int $count = 5): array
    {
        $current = (int) Setting::get('account_number_sequence', 0);
        $prefix  = strtoupper(substr($branch->code, 0, 3));
        $samples = [];

        for ($i = 1; $i <= $count; $i++) {
            $samples[] = $prefix . '1020' . str_pad($current + $i, 5, '0', STR_PAD_LEFT);
        }

        return $samples;
    }
}
