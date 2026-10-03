<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 20)->nullable()->unique()->after('id');
        });

        // Backfill existing staff with a generated ID so the column can be relied on immediately
        DB::table('users')->orderBy('id')->get(['id'])->each(function ($user, $i) {
            DB::table('users')->where('id', $user->id)->update([
                'staff_id' => 'STF-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_id']);
            $table->dropColumn('staff_id');
        });
    }
};
