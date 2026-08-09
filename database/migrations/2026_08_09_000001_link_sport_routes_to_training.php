<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->foreignId('sport_route_id')
                ->nullable()
                ->after('source_exercise_id')
                ->constrained('sport_routes')
                ->nullOnDelete();
        });

        Schema::table('training_logs', function (Blueprint $table) {
            $table->foreignId('sport_route_id')
                ->nullable()
                ->after('training_plan_item_id')
                ->constrained('sport_routes')
                ->nullOnDelete();
            $table->foreignId('sport_route_track_id')
                ->nullable()
                ->after('sport_route_id')
                ->constrained('sport_route_tracks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_route_track_id');
            $table->dropConstrainedForeignId('sport_route_id');
        });

        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_route_id');
        });
    }
};
