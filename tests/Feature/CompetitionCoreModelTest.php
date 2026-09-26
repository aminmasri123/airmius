<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionOpponent;
use App\Models\CompetitionRegistration;
use App\Models\CompetitionResult;
use App\Models\CompetitionRosterEntry;
use App\Models\CompetitionVenue;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompetitionCoreModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_competition_core_connects_season_class_opponent_venue_registration_roster_result_and_event(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $athlete = User::factory()->create(['name' => 'Mia Starter']);
        $competition = Competition::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'season_name' => 'Saison 2026',
            'season_key' => '2026',
            'name' => 'Landesmeisterschaft',
            'code' => 'LM-2026',
            'level' => 'regional',
            'status' => 'open',
            'starts_on' => '2026-06-20',
            'ends_on' => '2026-06-21',
            'registration_deadline_at' => '2026-06-01 18:00:00',
        ]);

        $class = CompetitionClass::query()->create([
            'competition_id' => $competition->id,
            'name' => 'U18 weiblich',
            'age_group' => 'U18',
            'gender' => 'female',
            'discipline' => '100m',
        ]);
        $opponent = CompetitionOpponent::query()->create([
            'competition_id' => $competition->id,
            'name' => 'Startgemeinschaft Nord',
        ]);
        $venue = CompetitionVenue::query()->create([
            'competition_id' => $competition->id,
            'name' => 'Sportpark Mitte',
            'city' => 'Saarbruecken',
        ]);
        $registration = CompetitionRegistration::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'team_id' => $team->id,
            'submitted_by' => $athlete->id,
            'status' => 'submitted',
            'submitted_at' => '2026-05-20 10:00:00',
        ]);
        $rosterEntry = CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_registration_id' => $registration->id,
            'competition_class_id' => $class->id,
            'user_id' => $athlete->id,
            'bib_number' => '42',
        ]);
        $event = Event::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'competition_venue_id' => $venue->id,
            'user_id' => $athlete->id,
            'title' => '100m Vorlauf',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => '2026-06-20 12:00:00',
        ]);
        $result = CompetitionResult::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'competition_opponent_id' => $opponent->id,
            'competition_roster_entry_id' => $rosterEntry->id,
            'event_id' => $event->id,
            'rank' => 1,
            'score' => 12.340,
            'result_text' => '12,34s',
            'recorded_at' => '2026-06-20 12:20:00',
        ]);

        $this->assertSame($club->id, $class->club_id);
        $this->assertSame($club->id, $venue->club_id);
        $this->assertSame($club->id, $event->club_id);
        $this->assertSame('Saison 2026', $competition->fresh()->season_name);
        $this->assertSame('Landesmeisterschaft', $event->fresh()->competition->name);
        $this->assertSame('12,34s', $competition->results()->first()->result_text);
        $this->assertSame($event->id, $result->event->id);
    }

    public function test_competition_models_reject_cross_club_links(): void
    {
        $homeClub = Club::factory()->create(['owner_id' => User::factory()]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $competition = Competition::query()->create([
            'club_id' => $homeClub->id,
            'name' => 'Cup',
            'status' => 'planned',
        ]);
        $foreignCompetition = Competition::query()->create([
            'club_id' => $otherClub->id,
            'name' => 'Other Cup',
            'status' => 'planned',
        ]);
        $foreignClass = CompetitionClass::query()->create([
            'competition_id' => $foreignCompetition->id,
            'name' => 'Foreign class',
        ]);

        $this->expectException(ValidationException::class);

        Event::query()->create([
            'club_id' => $homeClub->id,
            'competition_id' => $competition->id,
            'competition_class_id' => $foreignClass->id,
            'title' => 'Invalid heat',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => '2026-06-20 12:00:00',
        ]);
    }
}
