<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competition_tournament_groups')) {
            Schema::create('competition_tournament_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'comp_tour_groups_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'comp_tour_groups_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained(indexName: 'comp_tour_groups_class_fk')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'competition_class_id', 'name'], 'competition_tournament_group_unique_name');
            $table->index(['club_id', 'competition_id'], 'comp_tour_groups_club_comp_idx');
            });
        }

        if (! Schema::hasTable('competition_tournament_group_entries')) {
            Schema::create('competition_tournament_group_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'comp_tour_entries_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'comp_tour_entries_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_tournament_group_id')->constrained(indexName: 'comp_tour_entries_group_fk')->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained(indexName: 'comp_tour_entries_roster_fk')->nullOnDelete();
            $table->string('display_name');
            $table->unsignedSmallInteger('seed')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_tournament_group_id', 'competition_roster_entry_id'], 'competition_tournament_group_entry_unique_roster');
            $table->index(['club_id', 'competition_id'], 'comp_tour_entries_club_comp_idx');
            });
        }

        if (! Schema::hasTable('competition_tournament_matches')) {
            Schema::create('competition_tournament_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'comp_tour_matches_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'comp_tour_matches_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained(indexName: 'comp_tour_matches_class_fk')->nullOnDelete();
            $table->foreignId('competition_tournament_group_id')->nullable()->constrained(indexName: 'comp_tour_matches_group_fk')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'comp_tour_matches_event_fk')->nullOnDelete();
            $table->foreignId('home_roster_entry_id')->nullable()->constrained('competition_roster_entries', indexName: 'comp_tour_matches_home_fk')->nullOnDelete();
            $table->foreignId('away_roster_entry_id')->nullable()->constrained('competition_roster_entries', indexName: 'comp_tour_matches_away_fk')->nullOnDelete();
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
            $table->index(['club_id', 'competition_id', 'phase'], 'comp_tour_matches_club_comp_phase_idx');
            });
        }

        if (! Schema::hasTable('competition_tournament_standings')) {
            Schema::create('competition_tournament_standings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'comp_tour_standings_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'comp_tour_standings_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_tournament_group_id')->constrained(indexName: 'comp_tour_standings_group_fk')->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained(indexName: 'comp_tour_standings_roster_fk')->nullOnDelete();
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
            $table->index(['club_id', 'competition_id', 'rank'], 'comp_tour_standings_club_rank_idx');
            });
        }

        if (! Schema::hasTable('competition_tournament_corrections')) {
            Schema::create('competition_tournament_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'comp_tour_corrections_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'comp_tour_corrections_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_tournament_match_id')->nullable()->constrained(indexName: 'comp_tour_corrections_match_fk')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'comp_tour_corrections_actor_fk')->nullOnDelete();
            $table->string('correction_type');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('conflicts')->nullable();
            $table->string('status')->default('applied');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'correction_type'], 'comp_tour_corrections_type_idx');
            });
        }

        if (! Schema::hasTable('competition_lineups')) {
            Schema::create('competition_lineups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_lineups_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_lineups_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_registration_id')->nullable()->constrained(indexName: 'competition_lineups_registration_fk')->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained(indexName: 'competition_lineups_class_fk')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'competition_lineups_event_fk')->nullOnDelete();
            $table->string('sport_type')->nullable();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status'], 'competition_lineups_status_idx');
            });
        }

        if (! Schema::hasTable('competition_lineup_entries')) {
            Schema::create('competition_lineup_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_lineup_entries_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_lineup_entries_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_lineup_id')->constrained(indexName: 'competition_lineup_entries_lineup_fk')->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained(indexName: 'competition_lineup_entries_roster_fk')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(indexName: 'competition_lineup_entries_user_fk')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('role')->default('starter');
            $table->string('position')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id'], 'competition_lineup_entries_club_comp_idx');
            $table->unique(['competition_lineup_id', 'competition_roster_entry_id', 'role'], 'competition_lineup_entry_unique_roster_role');
            });
        }

        if (! Schema::hasTable('competition_start_list_entries')) {
            Schema::create('competition_start_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_start_entries_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_start_entries_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained(indexName: 'competition_start_entries_class_fk')->nullOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained(indexName: 'competition_start_entries_roster_fk')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'competition_start_entries_event_fk')->nullOnDelete();
            $table->string('heat')->nullable();
            $table->string('lane')->nullable();
            $table->string('start_number')->nullable();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->string('status')->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status'], 'competition_start_entries_status_idx');
            });
        }

        if (! Schema::hasTable('competition_relay_teams')) {
            Schema::create('competition_relay_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_relay_teams_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_relay_teams_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained(indexName: 'competition_relay_teams_class_fk')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'competition_relay_teams_event_fk')->nullOnDelete();
            $table->string('name');
            $table->string('discipline')->nullable();
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status'], 'competition_relay_teams_status_idx');
            });
        }

        if (! Schema::hasTable('competition_relay_legs')) {
            Schema::create('competition_relay_legs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_relay_legs_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_relay_legs_comp_fk')->cascadeOnDelete();
            $table->foreignId('competition_relay_team_id')->constrained(indexName: 'competition_relay_legs_team_fk')->cascadeOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained(indexName: 'competition_relay_legs_roster_fk')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(indexName: 'competition_relay_legs_user_fk')->nullOnDelete();
            $table->unsignedSmallInteger('leg_number');
            $table->string('segment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_relay_team_id', 'leg_number'], 'competition_relay_legs_team_leg_unique');
            $table->index(['club_id', 'competition_id'], 'competition_relay_legs_club_comp_idx');
            });
        }

        if (! Schema::hasTable('competition_substitutions')) {
            Schema::create('competition_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_substitutions_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_substitutions_comp_fk')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'competition_substitutions_event_fk')->nullOnDelete();
            $table->foreignId('out_roster_entry_id')->nullable()->constrained('competition_roster_entries', indexName: 'competition_substitutions_out_fk')->nullOnDelete();
            $table->foreignId('in_roster_entry_id')->nullable()->constrained('competition_roster_entries', indexName: 'competition_substitutions_in_fk')->nullOnDelete();
            $table->unsignedSmallInteger('minute')->nullable();
            $table->string('period')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id'], 'competition_substitutions_club_comp_idx');
            });
        }

        if (! Schema::hasTable('competition_playing_time_entries')) {
            Schema::create('competition_playing_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_playing_time_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_playing_time_comp_fk')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'competition_playing_time_event_fk')->nullOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained(indexName: 'competition_playing_time_roster_fk')->nullOnDelete();
            $table->unsignedSmallInteger('minutes_played')->default(0);
            $table->unsignedSmallInteger('started_period')->nullable();
            $table->unsignedSmallInteger('ended_period')->nullable();
            $table->json('segments')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'event_id', 'competition_roster_entry_id'], 'competition_playing_time_unique_entry');
            $table->index(['club_id', 'competition_id'], 'competition_playing_time_club_comp_idx');
            });
        }

        if (! Schema::hasTable('competition_official_assignments')) {
            Schema::create('competition_official_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'competition_officials_club_fk')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained(indexName: 'competition_officials_comp_fk')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained(indexName: 'competition_officials_event_fk')->nullOnDelete();
            $table->foreignId('competition_venue_id')->nullable()->constrained(indexName: 'competition_officials_venue_fk')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(indexName: 'competition_officials_user_fk')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('assignment_type')->default('referee');
            $table->string('role');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'assignment_type'], 'competition_officials_type_idx');
            });
        }
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
