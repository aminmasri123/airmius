<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('athlete_license_valid_until')->nullable()->after('athlete_license_number');
            $table->index('athlete_license_valid_until');
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            $table->date('athlete_license_valid_until')->nullable()->after('athlete_license_number');
            $table->index(['club_id', 'athlete_license_valid_until'], 'external_members_license_validity_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_external_members', function (Blueprint $table) {
            $table->dropIndex('external_members_license_validity_index');
            $table->dropColumn('athlete_license_valid_until');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['athlete_license_valid_until']);
            $table->dropColumn('athlete_license_valid_until');
        });
    }
};
