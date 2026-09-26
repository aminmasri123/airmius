<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('season_name')->nullable();
            $table->string('season_key')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('level')->nullable();
            $table->string('status')->default('planned');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('registration_deadline_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'season_key']);
            $table->unique(['club_id', 'code']);
        });

        Schema::create('competition_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('age_group')->nullable();
            $table->string('gender')->nullable();
            $table->string('discipline')->nullable();
            $table->json('rules')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_opponents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('club_name')->nullable();
            $table->string('external_identifier')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id', 'status']);
        });

        Schema::create('competition_roster_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('bib_number')->nullable();
            $table->string('role')->default('athlete');
            $table->string('status')->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
        });

        Schema::create('competition_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_opponent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('official');
            $table->integer('rank')->nullable();
            $table->decimal('score', 10, 3)->nullable();
            $table->string('result_text')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'competition_id']);
        });

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'competition_id')) {
                $table->foreignId('competition_id')->nullable()->after('sport_route_id')->constrained()->nullOnDelete();
                $table->foreignId('competition_class_id')->nullable()->after('competition_id')->constrained()->nullOnDelete();
                $table->foreignId('competition_venue_id')->nullable()->after('competition_class_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            foreach (['competition_venue_id', 'competition_class_id', 'competition_id'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::dropIfExists('competition_results');
        Schema::dropIfExists('competition_roster_entries');
        Schema::dropIfExists('competition_registrations');
        Schema::dropIfExists('competition_venues');
        Schema::dropIfExists('competition_opponents');
        Schema::dropIfExists('competition_classes');
        Schema::dropIfExists('competitions');
    }
};
