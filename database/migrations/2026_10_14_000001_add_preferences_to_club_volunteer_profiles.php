<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_volunteer_profiles', function (Blueprint $table) {
            $table->json('preferred_team_ids')->nullable();
            $table->json('preferred_areas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_volunteer_profiles', function (Blueprint $table) {
            $table->dropColumn(['preferred_team_ids', 'preferred_areas']);
        });
    }
};
