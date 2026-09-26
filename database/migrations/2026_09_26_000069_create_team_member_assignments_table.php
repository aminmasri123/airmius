<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_member_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_year_period_id')->nullable()->constrained('club_year_periods')->nullOnDelete();
            $table->foreignId('club_training_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 80);
            $table->string('position', 120)->nullable();
            $table->string('jersey_number', 24)->nullable();
            $table->string('status', 40)->default('active');
            $table->boolean('is_guest_participation')->default(false);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'user_id', 'sport_year_period_id'], 'team_member_assignment_member_season_idx');
            $table->index(['team_id', 'role', 'valid_from', 'valid_until'], 'team_member_assignment_team_role_validity_idx');
            $table->index(['club_training_group_id', 'valid_from', 'valid_until'], 'team_member_assignment_group_validity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_member_assignments');
    }
};
