<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\Ride;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use App\Services\TeamDailyLifeService;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamDailyLifeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_daily_life_bundles_attendance_carpools_tasks_cash_guardians_material_and_season(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create([
            'name' => 'Coach Ada',
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $yes = User::factory()->create([
            'name' => 'Yes Player',
            'birth_date' => now()->subYears(20)->toDateString(),
        ]);
        $missingMinor = User::factory()->create([
            'name' => 'Missing Junior',
            'birth_date' => now()->subYears(13)->toDateString(),
            'guardian_email' => 'parent@example.com',
        ]);
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Team Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U15 Alltag',
            'sport_type' => 'football',
        ]);

        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($yes->id, ['role' => TeamRoles::PLAYER]);
        $team->users()->attach($missingMinor->id, ['role' => TeamRoles::PLAYER]);

        $nextEvent = Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $coach->id,
            'title' => 'Saturday match',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDays(2),
            'location_name' => 'Home pitch',
            'participant_response_required' => true,
            'participant_response_deadline_at' => now()->addDay(),
        ]);
        $nextEvent->participants()->attach($yes->id, [
            'status' => 'yes',
            'response_mode' => 'self',
            'responded_at' => now(),
        ]);

        Ride::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'event_id' => $nextEvent->id,
            'driver_id' => $coach->id,
            'visibility' => 'team',
            'from' => 'Station',
            'to' => 'Home pitch',
            'departure_time' => now()->addDays(2)->subHour(),
            'seats' => 4,
            'contact_details' => 'Chat',
        ])->users()->attach($coach->id, [
            'status' => Ride::MEMBER_STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $missingMinor->id,
            'collector_id' => $coach->id,
            'category' => 'equipment',
            'amount' => 12.5,
            'currency' => 'EUR',
            'status' => 'open',
            'due_date' => now()->addWeek(),
        ]);

        Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $coach->id,
            'title' => 'Next training',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDays(5),
        ]);

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/teams/'.$team->id.'/daily-life')
            ->assertOk()
            ->assertJsonPath('data.version', '2026-08-08.airmius_team_home.v2')
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.team.viewer_role', TeamRoles::COACH)
            ->assertJsonPath('data.access.can_manage_operations', true)
            ->assertJsonPath('data.today.next_event_id', $nextEvent->id)
            ->assertJsonPath('data.today.primary_action.key', 'remind_missing_responses')
            ->assertJsonPath('data.attendance.next_event.title', 'Saturday match')
            ->assertJsonPath('data.attendance.summary.team_size', 3)
            ->assertJsonPath('data.attendance.summary.responded', 1)
            ->assertJsonPath('data.attendance.summary.missing', 2)
            ->assertJsonPath('data.attendance.missing_responses.0.name', 'Coach Ada')
            ->assertJsonMissingPath('data.attendance.missing_responses.0.email')
            ->assertJsonPath('data.carpools.count', 1)
            ->assertJsonPath('data.carpools.available_seats_total', 3)
            ->assertJsonPath('data.tasks.items.0.key', 'remind_missing_responses')
            ->assertJsonPath('data.cash_box.counts.open', 1)
            ->assertJsonPath('data.cash_box.open_items.0.member_name', 'Missing Junior')
            ->assertJsonPath('data.guardian_mode.recommended', true)
            ->assertJsonPath('data.guardian_mode.minor_members_count', 1)
            ->assertJsonPath('data.materials.suggested_lists.3.key', 'jerseys')
            ->assertJsonPath('data.season_planning.planning_state', 'needs_more_events')
            ->assertJsonPath('data.mobile_contract.write_endpoints.attendance', '/api/v1/events/{event}/participation')
            ->assertJsonPath('data.mobile_contract.write_endpoints.carpools', '/api/v1/rides')
            ->assertJsonPath('data.mobile_contract.write_endpoints.fees', '/api/v1/teams/{team}/penalty-fees')
            ->assertDontSee('parent@example.com');
    }

    public function test_regular_member_receives_only_own_fees_and_no_response_or_guardian_contacts(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Current Player']);
        $other = User::factory()->create([
            'name' => 'Private Teammate',
            'guardian_email' => 'private-parent@example.com',
            'birth_date' => now()->subYears(14)->toDateString(),
        ]);
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Privacy Club']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($member->id, ['role' => TeamRoles::PLAYER]);
        $team->users()->attach($other->id, ['role' => TeamRoles::PLAYER]);
        $event = Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Privacy Match',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'participant_response_required' => true,
        ]);
        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'collector_id' => $owner->id,
            'category' => 'equipment',
            'amount' => 5,
            'currency' => 'EUR',
            'status' => 'open',
        ]);
        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $other->id,
            'collector_id' => $owner->id,
            'category' => 'private-fee-marker',
            'amount' => 99,
            'currency' => 'EUR',
            'status' => 'open',
        ]);

        Sanctum::actingAs($member);

        $response = $this->withHeader('X-Locale', 'ar')
            ->getJson('/api/v1/teams/'.$team->id.'/daily-life')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertHeader('X-Airmius-Text-Direction', 'rtl')
            ->assertJsonPath('data.access.is_team_member', true)
            ->assertJsonPath('data.access.can_manage_operations', false)
            ->assertJsonPath('data.attendance.next_event.id', $event->id)
            ->assertJsonPath('data.attendance.missing_responses', [])
            ->assertJsonPath('data.today.primary_action.key', 'confirm_attendance')
            ->assertJsonPath('data.today.primary_action.label', 'الرد على الحضور')
            ->assertJsonPath('data.cash_box.scope', 'self')
            ->assertJsonPath('data.cash_box.counts.open', 1)
            ->assertJsonPath('data.cash_box.totals.open', 5)
            ->assertJsonPath('data.cash_box.open_items.0.member_id', $member->id)
            ->assertJsonPath('data.guardian_mode.visible', false);

        $response->assertDontSee('Private Teammate');
        $response->assertDontSee('private-parent@example.com');
        $response->assertDontSee('private-fee-marker');
        $response->assertJsonMissing(['amount' => 99]);
    }

    public function test_team_home_query_budget_is_bounded_and_locales_have_placeholder_parity(): void
    {
        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Query Club']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($owner->id, ['role' => TeamRoles::COACH]);
        Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Budget Event',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TeamDailyLifeService::class)->forTeam($team, $owner);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(14, $queryCount, "Team home used {$queryCount} queries");

        $reference = Arr::dot(require lang_path('de/team_home.php'));
        foreach (['en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path("{$locale}/team_home.php"));
            $this->assertSame(array_keys($reference), array_keys($catalog), "team_home:{$locale} key parity");

            foreach ($reference as $key => $source) {
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $source, $sourceMatches);
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $catalog[$key], $translatedMatches);
                $this->assertSame($sourceMatches[0], $translatedMatches[0], "team_home:{$locale} placeholder parity for {$key}");
            }
        }

        $profile = (string) file_get_contents(resource_path('js/Pages/Auth/Dashboard/Teams/Profile.vue'));
        $widget = (string) file_get_contents(resource_path('js/Components/Teams/TeamDailyHomeWidget.vue'));
        $this->assertStringContainsString("import TeamDailyHomeWidget from '@/Components/Teams/TeamDailyHomeWidget.vue'", $profile);
        $this->assertStringContainsString('<TeamDailyHomeWidget :team-id="teamProfile.id" />', $profile);
        $this->assertStringContainsString('/daily-life', $widget);
        $this->assertStringContainsString('AbortController', $widget);
        $this->assertStringNotContainsString('setInterval', $widget);
    }

    public function test_club_member_outside_team_receives_aggregates_but_no_team_only_operations(): void
    {
        $owner = User::factory()->create();
        $observer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Hidden Team Player']);
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Observer Club']);
        $club->users()->attach($observer->id, ['role' => 'member']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($player->id, ['role' => TeamRoles::PLAYER]);
        $event = Event::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Club-visible event',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);
        Ride::query()->create([
            'team_id' => $team->id,
            'club_id' => $club->id,
            'event_id' => $event->id,
            'driver_id' => $player->id,
            'visibility' => 'team',
            'from' => 'PRIVATE-PICKUP',
            'to' => 'PRIVATE-DESTINATION',
            'departure_time' => now()->addDay(),
            'seats' => 3,
        ]);

        Sanctum::actingAs($observer);

        $response = $this->getJson('/api/v1/teams/'.$team->id.'/daily-life')
            ->assertOk()
            ->assertJsonPath('data.access.is_team_member', false)
            ->assertJsonPath('data.access.can_manage_operations', false)
            ->assertJsonPath('data.attendance.summary.team_size', 1)
            ->assertJsonPath('data.attendance.missing_responses', [])
            ->assertJsonPath('data.carpools.items', [])
            ->assertJsonPath('data.cash_box.scope', 'self')
            ->assertJsonPath('data.cash_box.counts.open', 0)
            ->assertJsonPath('data.guardian_mode.visible', false);

        $response->assertDontSee('Hidden Team Player');
        $response->assertDontSee('PRIVATE-PICKUP');
        $response->assertDontSee('PRIVATE-DESTINATION');
    }
}
