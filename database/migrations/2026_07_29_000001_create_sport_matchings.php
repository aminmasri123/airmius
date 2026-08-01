<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sport_matchings')) {
            Schema::create('sport_matchings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
                $table->string('mode', 24);
                $table->string('title', 140);
                $table->text('description')->nullable();
                $table->string('city', 120);
                $table->string('country_code', 2);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedSmallInteger('radius_km')->default(25);
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->unsignedSmallInteger('participants_needed')->default(1);
                $table->unsignedSmallInteger('team_size')->nullable();
                $table->string('skill_level', 24)->default('all');
                $table->string('status', 24)->default('open');
                $table->timestamps();

                $table->index(['mode', 'status', 'starts_at']);
                $table->index(['sport_id', 'city', 'status']);
            });
        }

        if (! Schema::hasTable('sport_matching_applications')) {
            Schema::create('sport_matching_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sport_matching_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
                $table->text('message')->nullable();
                $table->string('status', 24)->default('pending');
                $table->timestamps();

                $table->unique(['sport_matching_id', 'user_id', 'team_id'], 'sport_matching_application_unique');
                $table->index(['sport_matching_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_matching_applications');
        Schema::dropIfExists('sport_matchings');
    }
};
