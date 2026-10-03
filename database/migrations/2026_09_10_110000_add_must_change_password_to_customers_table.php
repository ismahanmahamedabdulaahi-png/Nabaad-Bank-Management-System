<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            // Doubles as the "this invite link was already used" guard, same as
            // users.invitation_accepted_at in the staff invitation flow.
            $table->timestamp('invitation_accepted_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'invitation_accepted_at']);
        });
    }
};
