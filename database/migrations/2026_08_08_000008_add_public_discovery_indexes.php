<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('clubs', 'clubs_public_city_idx')) {
            Schema::table('clubs', function (Blueprint $table): void {
                $table->index(
                    ['verification_status', 'is_listed', 'city'],
                    'clubs_public_city_idx',
                );
            });
        }

        if (! Schema::hasIndex('clubs', 'clubs_public_sport_idx')) {
            Schema::table('clubs', function (Blueprint $table): void {
                $table->index(
                    ['verification_status', 'is_listed', 'sport_type'],
                    'clubs_public_sport_idx',
                );
            });
        }

        if (! Schema::hasIndex('events', 'events_public_city_start_idx')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->index(
                    ['visibility', 'status', 'location_city', 'start_time'],
                    'events_public_city_start_idx',
                );
            });
        }

        if (! Schema::hasIndex('teams', 'teams_sport_club_idx')) {
            Schema::table('teams', function (Blueprint $table): void {
                $table->index(['sport_type', 'club_id'], 'teams_sport_club_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('teams', 'teams_sport_club_idx')) {
            Schema::table('teams', function (Blueprint $table): void {
                $table->dropIndex('teams_sport_club_idx');
            });
        }

        if (Schema::hasIndex('events', 'events_public_city_start_idx')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropIndex('events_public_city_start_idx');
            });
        }

        if (Schema::hasIndex('clubs', 'clubs_public_sport_idx')) {
            Schema::table('clubs', function (Blueprint $table): void {
                $table->dropIndex('clubs_public_sport_idx');
            });
        }

        if (Schema::hasIndex('clubs', 'clubs_public_city_idx')) {
            Schema::table('clubs', function (Blueprint $table): void {
                $table->dropIndex('clubs_public_city_idx');
            });
        }
    }
};
