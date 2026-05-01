<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('team_invitations', 'recipient_id') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE team_invitations MODIFY recipient_id BIGINT UNSIGNED NULL');
        }

        Schema::table('team_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('team_invitations', 'email')) {
                $table->string('email')->nullable()->after('recipient_id');
            }

            if (! Schema::hasColumn('team_invitations', 'token')) {
                $table->string('token', 80)->nullable()->unique()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('team_invitations', 'token')) {
                $table->dropUnique(['token']);
                $table->dropColumn('token');
            }

            if (Schema::hasColumn('team_invitations', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
