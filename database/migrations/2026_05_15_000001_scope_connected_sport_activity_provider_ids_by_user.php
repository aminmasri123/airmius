<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connected_sport_activities', function (Blueprint $table) {
            $table->dropUnique('connected_sport_activities_provider_provider_activity_id_unique');
            $table->unique(['user_id', 'provider', 'provider_activity_id'], 'connected_sport_activities_user_provider_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('connected_sport_activities', function (Blueprint $table) {
            $table->dropUnique('connected_sport_activities_user_provider_activity_unique');
            $table->unique(['provider', 'provider_activity_id']);
        });
    }
};
