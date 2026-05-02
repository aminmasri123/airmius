<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'athlete_license_number')) {
                $table->string('athlete_license_number', 120)->nullable()->after('status');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'is_official')) {
                $table->boolean('is_official')->default(false)->after('sport_type');
            }

            if (! Schema::hasColumn('clubs', 'official_club_number')) {
                $table->string('official_club_number', 120)->nullable()->after('is_official');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'official_club_number')) {
                $table->dropColumn('official_club_number');
            }

            if (Schema::hasColumn('clubs', 'is_official')) {
                $table->dropColumn('is_official');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'athlete_license_number')) {
                $table->dropColumn('athlete_license_number');
            }
        });
    }
};
