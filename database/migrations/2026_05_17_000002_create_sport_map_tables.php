<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('sport_type', 80)->nullable();
            $table->string('visibility', 30)->default('private');
            $table->string('status', 30)->default('planned');
            $table->string('difficulty', 30)->nullable();
            $table->string('surface', 60)->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->string('start_name', 120)->nullable();
            $table->string('end_name', 120)->nullable();
            $table->unsignedInteger('distance_meters')->default(0);
            $table->unsignedInteger('estimated_duration_seconds')->nullable();
            $table->unsignedInteger('elevation_gain_meters')->default(0);
            $table->unsignedInteger('elevation_loss_meters')->default(0);
            $table->json('waypoints');
            $table->json('route_geometry')->nullable();
            $table->json('navigation_cues')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['visibility', 'status']);
            $table->index(['sport_type', 'visibility']);
            $table->index(['team_id', 'visibility']);
        });

        Schema::create('sport_route_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('sport_type', 80)->nullable();
            $table->string('status', 30)->default('recording');
            $table->string('source', 40)->default('airmius');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('distance_meters')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('elevation_gain_meters')->default(0);
            $table->unsignedInteger('elevation_loss_meters')->default(0);
            $table->decimal('average_speed_mps', 8, 3)->nullable();
            $table->decimal('max_speed_mps', 8, 3)->nullable();
            $table->json('track_points')->nullable();
            $table->json('track_geometry')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'started_at']);
            $table->index(['sport_route_id', 'status']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('sport_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('type', 80);
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('visibility', 30)->default('public');
            $table->string('status', 30)->default('active');
            $table->json('sport_types')->nullable();
            $table->json('amenities')->nullable();
            $table->json('surfaces')->nullable();
            $table->string('opening_hours', 255)->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index(['visibility', 'status']);
            $table->index(['latitude', 'longitude']);
            $table->index(['team_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_places');
        Schema::dropIfExists('sport_route_tracks');
        Schema::dropIfExists('sport_routes');
    }
};
