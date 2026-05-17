<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('guardian_email')
            ->update([
                'guardian_email' => DB::raw('LOWER(TRIM(guardian_email))'),
            ]);

        DB::table('guardian_access_codes')
            ->whereNotNull('email')
            ->update([
                'email' => DB::raw('LOWER(TRIM(email))'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->index(['guardian_email', 'birth_date'], 'users_guardian_email_birth_date_index');
            $table->index(['guardian_user_id', 'birth_date'], 'users_guardian_user_birth_date_index');
        });

        Schema::table('guardian_access_codes', function (Blueprint $table) {
            $table->index(['email', 'used_at', 'expires_at', 'id'], 'guardian_access_codes_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_access_codes', function (Blueprint $table) {
            $table->dropIndex('guardian_access_codes_lookup_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_guardian_user_birth_date_index');
            $table->dropIndex('users_guardian_email_birth_date_index');
        });
    }
};
