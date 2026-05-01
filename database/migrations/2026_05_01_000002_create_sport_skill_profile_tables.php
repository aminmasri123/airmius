<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sport_id', 'key']);
        });

        Schema::create('user_sports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->string('experience_level')->default('beginner');
            $table->string('visibility')->default('public');
            $table->timestamps();

            $table->unique(['user_id', 'sport_id']);
        });

        Schema::create('user_sport_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_skill_id')->constrained()->cascadeOnDelete();
            $table->string('self_level')->default('learning');
            $table->boolean('is_visible')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sport_skill_id']);
        });

        Schema::create('skill_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_sport_skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endorser_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->default('visitor');
            $table->string('level')->default('confirmed');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_sport_skill_id', 'endorser_id']);
        });

        Schema::create('profile_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->default('visitor');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('gamification_xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type')->default('sportler');
            $table->nullableMorphs('source');
            $table->integer('amount');
            $table->string('reason');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_xp_events');
        Schema::dropIfExists('profile_recommendations');
        Schema::dropIfExists('skill_endorsements');
        Schema::dropIfExists('user_sport_skills');
        Schema::dropIfExists('user_sports');
        Schema::dropIfExists('sport_skills');
    }
};
