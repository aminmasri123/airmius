<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_tournament_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'competition_class_id', 'name'], 'competition_tournament_group_unique_name');
            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_tournament_group_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_tournament_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->unsignedSmallInteger('seed')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_tournament_group_id', 'competition_roster_entry_id'], 'competition_tournament_group_entry_unique_roster');
            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_tournament_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_tournament_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('home_roster_entry_id')->nullable()->constrained('competition_roster_entries')->nullOnDelete();
            $table->foreignId('away_roster_entry_id')->nullable()->constrained('competition_roster_entries')->nullOnDelete();
            $table->string('phase')->default('group');
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->unsignedSmallInteger('match_number')->default(1);
            $table->string('home_label')->nullable();
            $table->string('away_label')->nullable();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->string('status')->default('planned');
            $table->timestamp('scheduled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'phase', 'round_number', 'match_number'], 'competition_tournament_match_slot_unique');
            $table->index(['club_id', 'competition_id', 'phase']);
        });

        Schema::create('competition_tournament_standings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_tournament_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->unsignedSmallInteger('rank')->default(0);
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->integer('score_for')->default(0);
            $table->integer('score_against')->default(0);
            $table->integer('score_difference')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_tournament_group_id', 'competition_roster_entry_id'], 'competition_tournament_standing_unique_roster');
            $table->index(['club_id', 'competition_id', 'rank']);
        });

        Schema::create('competition_tournament_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_tournament_match_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('correction_type');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('conflicts')->nullable();
            $table->string('status')->default('applied');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'correction_type']);
        });

        Schema::create('competition_lineups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sport_type')->nullable();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status']);
        });

        Schema::create('competition_lineup_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_lineup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('role')->default('starter');
            $table->string('position')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
            $table->unique(['competition_lineup_id', 'competition_roster_entry_id', 'role'], 'competition_lineup_entry_unique_roster_role');
        });

        Schema::create('competition_start_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('heat')->nullable();
            $table->string('lane')->nullable();
            $table->string('start_number')->nullable();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->string('status')->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status']);
        });

        Schema::create('competition_relay_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('discipline')->nullable();
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status']);
        });

        Schema::create('competition_relay_legs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_relay_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('leg_number');
            $table->string('segment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_relay_team_id', 'leg_number']);
            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('out_roster_entry_id')->nullable()->constrained('competition_roster_entries')->nullOnDelete();
            $table->foreignId('in_roster_entry_id')->nullable()->constrained('competition_roster_entries')->nullOnDelete();
            $table->unsignedSmallInteger('minute')->nullable();
            $table->string('period')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_playing_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('minutes_played')->default(0);
            $table->unsignedSmallInteger('started_period')->nullable();
            $table->unsignedSmallInteger('ended_period')->nullable();
            $table->json('segments')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'event_id', 'competition_roster_entry_id'], 'competition_playing_time_unique_entry');
            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_official_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('assignment_type')->default('referee');
            $table->string('role');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'assignment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_official_assignments');
        Schema::dropIfExists('competition_playing_time_entries');
        Schema::dropIfExists('competition_substitutions');
        Schema::dropIfExists('competition_relay_legs');
        Schema::dropIfExists('competition_relay_teams');
        Schema::dropIfExists('competition_start_list_entries');
        Schema::dropIfExists('competition_lineup_entries');
        Schema::dropIfExists('competition_lineups');
        Schema::dropIfExists('competition_tournament_corrections');
        Schema::dropIfExists('competition_tournament_standings');
        Schema::dropIfExists('competition_tournament_matches');
        Schema::dropIfExists('competition_tournament_group_entries');
        Schema::dropIfExists('competition_tournament_groups');
    }
};
