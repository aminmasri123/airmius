<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_volunteer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('skills')->nullable();
            $table->json('interests')->nullable();
            $table->json('availability')->nullable();
            $table->unsignedSmallInteger('workload_limit_minutes_per_week')->nullable();
            $table->string('visibility', 30)->default('club_managers');
            $table->timestamps();

            $table->unique(['club_id', 'user_id']);
            $table->index(['club_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_volunteer_profiles');
    }
};
