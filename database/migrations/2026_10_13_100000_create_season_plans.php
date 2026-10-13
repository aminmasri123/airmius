<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_year_period_id')->constrained('club_year_periods')->restrictOnDelete();
            $table->string('name');
            $table->text('goals')->nullable();
            $table->string('status')->default('draft');
            $table->json('phases');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('season_plan_events', function (Blueprint $table): void {
            $table->foreignId('season_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->primary(['season_plan_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_plan_events');
        Schema::dropIfExists('season_plans');
    }
};
