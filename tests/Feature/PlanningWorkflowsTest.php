<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\ClubYearPeriod;
use App\Models\Competition;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\GuardianChildRelationship;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\PlatformModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanningWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_routes_have_explicit_processing_purposes_and_private_cache_policy(): void
    {
        [$owner, $club, $team] = $this->fixture();
        $competition = Competition::create(['club_id' => $club->id, 'name' => 'Purpose Cup', 'status' => 'open']);
        Sanctum::actingAs($owner);
        foreach ([
            '/api/v1/planning/workspace' => ['organization', 'members', 'sensitive'],
            '/api/v1/family-calendar' => ['organization', 'members', 'sensitive'],
            "/api/v1/clubs/{$club->id}/competitions" => ['organization', 'members', 'sensitive'],
            "/api/v1/competitions/{$competition->id}" => ['organization', 'members', 'sensitive'],
            "/api/v1/teams/{$team->id}/season-plans" => ['training', 'training', 'highly_sensitive'],
        ] as $url => [$purpose, $module, $classification]) {
            $response = $this->getJson($url)->assertOk()
                ->assertHeader('X-Airmius-Data-Purpose', $purpose)->assertHeader('X-Airmius-Module', $module);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $resolved = PlatformModuleRegistry::forApiPath($url);
            $this->assertSame($classification, $resolved['module']['data_classification']->value);
        }
        foreach ([
            "/api/v1/teams/{$team->id}/season-plans/123",
            '/api/v1/family-calendar/entries/123',
            "/api/v1/competitions/{$competition->id}/registrations/123/paid",
        ] as $url) {
            $this->assertNotNull(PlatformModuleRegistry::forApiPath($url));
        }
        $invalid = $this->postJson('/api/v1/family-calendar/entries', [])->assertUnprocessable();
        $this->assertStringContainsString('no-store', $invalid->headers->get('Cache-Control'));
        $missing = $this->getJson('/api/v1/competitions/999999')->assertNotFound();
        $this->assertStringContainsString('no-store', $missing->headers->get('Cache-Control'));
        Sanctum::actingAs(User::factory()->create());
        $denied = $this->getJson("/api/v1/clubs/{$club->id}/competitions")->assertForbidden();
        $this->assertStringContainsString('no-store', $denied->headers->get('Cache-Control'));
    }

    public function test_unauthenticated_planning_responses_are_not_cacheable(): void
    {
        foreach (['/api/v1/planning/workspace', '/api/v1/family-calendar', '/api/v1/competitions/999999', '/api/v1/teams/999999/season-plans'] as $url) {
            $response = $this->getJson($url)->assertUnauthorized();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_published_plan_does_not_reveal_hidden_linked_events_and_drafts_are_internal(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        $hidden = $this->event($owner, $club, $team, $period);
        $member = User::factory()->create();
        $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
        Sanctum::actingAs($owner);
        $draft = $this->postJson("/api/v1/teams/{$team->id}/season-plans", $this->season($period, [$hidden->id]))->assertCreated()->json('data.id');
        $published = $this->season($period, [$hidden->id]);
        $published['name'] = 'Published';
        $published['status'] = 'published';
        $this->postJson("/api/v1/teams/{$team->id}/season-plans", $published)->assertCreated();
        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertOk()
            ->assertJsonCount(1, 'data.plans')->assertJsonPath('data.plans.0.name', 'Published')
            ->assertJsonCount(0, 'data.plans.0.events')->assertJsonCount(0, 'data.events')->assertJsonPath('data.can_edit', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($hidden->title, $response->getContent());
        $this->putJson("/api/v1/teams/{$team->id}/season-plans/$draft", $this->season($period) + ['revision' => 1])->assertForbidden();
    }

    public function test_saving_goals_preserves_event_links_hidden_from_the_current_editor(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        $hidden = $this->event($owner, $club, $team, $period);
        Sanctum::actingAs($owner);
        $payload = $this->season($period, [$hidden->id]);
        $id = $this->postJson("/api/v1/teams/{$team->id}/season-plans", $payload)->assertCreated()->json('data.id');
        $editor = User::factory()->create();
        $club->users()->attach($editor, ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => [ClubPermissions::TRAINING_PLANS_EDIT => true]]);
        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertOk()->assertJsonCount(0, 'data.plans.0.events')->assertJsonPath('data.can_edit', true);
        $payload['event_ids'] = [];
        $payload['revision'] = 1;
        $payload['goals'] = 'Updated goals';
        $this->putJson("/api/v1/teams/{$team->id}/season-plans/$id", $payload)->assertOk()->assertJsonCount(0, 'data.events');
        $this->assertDatabaseHas('season_plan_events', ['season_plan_id' => $id, 'event_id' => $hidden->id]);
        $otherPeriod = ClubYearPeriod::create(['club_id' => $club->id, 'type' => 'sport', 'name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $payload['sport_year_period_id'] = $otherPeriod->id;
        $payload['revision'] = 2;
        $payload['phases'][0]['starts_on'] = '2027-10-01';
        $payload['phases'][0]['ends_on'] = '2027-10-31';
        $this->putJson("/api/v1/teams/{$team->id}/season-plans/$id", $payload)->assertUnprocessable();
    }

    public function test_family_capacity_waitlist_and_cancelled_event_are_not_bypassed(): void
    {
        [$guardian, $club, $team, $period] = $this->fixture();
        $child = User::factory()->create(['birth_date' => now()->subYears(10), 'guardian_consent_at' => now()]);
        $club->users()->attach($child, ['role' => 'member', 'membership_status' => 'active']);
        $team->users()->attach($child, ['role' => 'player']);
        GuardianChildRelationship::create(['club_id' => $club->id, 'child_user_id' => $child->id, 'guardian_user_id' => $guardian->id, 'status' => 'accepted']);
        $event = $this->event($guardian, $club, $team, $period);
        $event->update(['max_participants' => 1]);
        EventParticipant::create(['event_id' => $event->id, 'user_id' => $guardian->id, 'status' => 'yes']);
        Sanctum::actingAs($guardian);
        $url = "/api/v1/family-calendar/events/{$event->id}/people/{$child->id}/response";
        $this->postJson($url, ['status' => 'yes'])->assertOk()->assertJsonPath('data.response_status', 'waitlist');
        $this->assertDatabaseHas('event_participants', ['event_id' => $event->id, 'user_id' => $child->id, 'waitlist_position' => 1]);
        $event->update(['status' => 'cancelled']);
        $this->postJson($url, ['status' => 'yes'])->assertForbidden();
    }

    public function test_family_entry_edit_delete_and_child_club_boundaries_persist(): void
    {
        [$guardian, $club, $team, $period] = $this->fixture();
        Sanctum::actingAs($guardian);
        $payload = ['title' => 'Appointment', 'starts_at' => '2026-10-25T08:00:00Z', 'ends_at' => '2026-10-25T09:00:00Z'];
        $entry = $this->postJson('/api/v1/family-calendar/entries', $payload)->assertCreated()->json('data.id');
        $payload['title'] = 'Changed';
        $this->putJson("/api/v1/family-calendar/entries/$entry", $payload)->assertOk();
        $this->getJson('/api/v1/family-calendar?month=2026-10')->assertOk()->assertJsonPath('data.entries.0.title', 'Changed');
        $unrelatedChild = User::factory()->create(['birth_date' => now()->subYears(10)]);
        $this->postJson('/api/v1/family-calendar/entries', $payload + ['child_user_id' => $unrelatedChild->id, 'club_id' => $club->id])->assertForbidden();
        $this->deleteJson("/api/v1/family-calendar/entries/$entry")->assertOk();
        $this->assertDatabaseMissing('family_calendar_entries', ['id' => $entry]);
        $this->getJson('/api/v1/family-calendar?month=invalid')->assertUnprocessable();
    }

    public function test_pending_club_member_and_foreign_team_reader_cannot_access_planning(): void
    {
        [$owner, $club, $team] = $this->fixture();
        $pending = User::factory()->create();
        $club->users()->attach($pending, ['role' => 'member', 'membership_status' => 'pending']);
        $team->users()->attach($pending, ['role' => 'player']);
        Sanctum::actingAs($pending);
        $this->getJson('/api/v1/planning/workspace')->assertOk()->assertJsonCount(0, 'data.clubs');
        $this->getJson("/api/v1/clubs/{$club->id}/competitions")->assertForbidden();
        $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertForbidden();
    }

    public function test_family_offset_times_round_trip_and_month_uses_account_timezone(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        $owner->update(['timezone' => 'Europe/Berlin']);
        Sanctum::actingAs($owner);
        $entry = $this->postJson('/api/v1/family-calendar/entries', [
            'title' => 'Month boundary', 'starts_at' => '2026-10-01T00:30:00+02:00',
            'ends_at' => '2026-10-01T01:00:00+02:00',
        ])->assertCreated()->assertJsonPath('data.starts_at', '2026-09-30T22:30:00.000000Z')->json('data.id');
        $this->assertDatabaseHas('family_calendar_entries', ['id' => $entry, 'starts_at' => '2026-09-30 22:30:00']);
        $event = $this->event($owner, $club, $team, $period);
        $event->update(['start_time' => '2026-09-30 22:30:00', 'end_time' => '2026-09-30 23:00:00']);
        $this->getJson('/api/v1/family-calendar?month=2026-10')->assertOk()
            ->assertJsonPath('data.month', '2026-10')->assertJsonPath('data.entries.0.id', $entry)
            ->assertJsonPath('data.events.0.id', $event->id);
        $this->getJson('/api/v1/family-calendar?month=2026-09')->assertOk()
            ->assertJsonCount(0, 'data.entries')->assertJsonCount(0, 'data.events');
        $this->putJson("/api/v1/family-calendar/entries/$entry", [
            'title' => 'After DST', 'starts_at' => '2026-10-25T02:30:00+01:00',
            'ends_at' => '2026-10-25T03:00:00+01:00',
        ])->assertOk()->assertJsonPath('data.starts_at', '2026-10-25T01:30:00.000000Z');
    }

    public function test_finance_permissions_are_independent_of_competition_edit_and_private_fields_are_omitted(): void
    {
        [$owner, $club] = $this->fixture();
        $editor = User::factory()->create();
        $member = User::factory()->create();
        $athlete = User::factory()->create(['athlete_license_number' => 'L1', 'athlete_license_valid_until' => now()->addYear()]);
        foreach ([$editor, $member, $athlete] as $user) {
            $club->users()->attach($user, ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => $user->is($editor) ? [ClubPermissions::EVENTS_EDIT => true] : []]);
        }
        $competition = Competition::create(['club_id' => $club->id, 'name' => 'Fee Cup', 'status' => 'open', 'starts_on' => now()->addDays(10)]);
        Sanctum::actingAs($editor);
        $roster = $this->postJson("/api/v1/competitions/{$competition->id}/components/roster", ['display_name' => 'Athlete', 'user_id' => $athlete->id])->assertCreated()->json('data.id');
        $registration = $this->postJson("/api/v1/competitions/{$competition->id}/registrations", ['roster_ids' => [$roster], 'start_fee_cents' => 2500])->assertCreated()->json('data.id');
        $url = "/api/v1/competitions/{$competition->id}/registrations/$registration";
        $this->postJson("$url/eligibility")->assertOk();
        $this->postJson("$url/paid")->assertForbidden();
        $this->postJson("$url/confirm")->assertForbidden();
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/competitions/{$competition->id}")->assertOk()
            ->assertJsonPath('data.can_edit', false)->assertJsonPath('data.can_view_finance', false)
            ->assertJsonMissingPath('data.registrations.0.start_fee_cents')->assertJsonMissingPath('data.registrations.0.payload');
        $this->postJson("$url/eligibility")->assertForbidden();
        Sanctum::actingAs($owner);
        $this->postJson("$url/confirm")->assertUnprocessable();
        $this->postJson("$url/paid")->assertOk();
        $this->postJson("$url/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_cross_selector_plan_registration_roster_and_match_ids_are_rejected(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        $other = Team::factory()->create(['club_id' => $club->id]);
        Sanctum::actingAs($owner);
        $planId = $this->postJson("/api/v1/teams/{$team->id}/season-plans", $this->season($period))->assertCreated()->json('data.id');
        $this->putJson("/api/v1/teams/{$other->id}/season-plans/$planId", $this->season($period) + ['revision' => 1])->assertNotFound();
        $this->deleteJson("/api/v1/teams/{$other->id}/season-plans/$planId")->assertNotFound();
        $competition = Competition::create(['club_id' => $club->id, 'name' => 'One', 'status' => 'open']);
        $otherCompetition = Competition::create(['club_id' => $club->id, 'name' => 'Two', 'status' => 'open']);
        $roster = $this->postJson("/api/v1/competitions/{$otherCompetition->id}/components/roster", ['display_name' => 'Other'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/competitions/{$competition->id}/registrations", ['roster_ids' => [$roster], 'start_fee_cents' => 0])->assertUnprocessable();
        $this->deleteJson("/api/v1/competitions/{$competition->id}/components/roster/$roster")->assertNotFound();
        $this->postJson("/api/v1/competitions/{$competition->id}/lineups", ['name' => 'One', 'entries' => [['competition_roster_entry_id' => $roster, 'role' => 'starter']]])->assertUnprocessable();
    }

    public function test_sport_year_with_saved_plan_cannot_be_deleted_even_without_events_or_assigned_team(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/teams/{$team->id}/season-plans", $this->season($period))->assertCreated();
        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$period->id}")->assertUnprocessable();
    }

    public function test_season_save_reload_update_and_stale_revision_keep_events_unchanged(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        $event = $this->event($owner, $club, $team, $period);
        Sanctum::actingAs($owner);
        $payload = $this->season($period, [$event->id]);
        $id = $this->postJson("/api/v1/teams/{$team->id}/season-plans", $payload)->assertCreated()->json('data.id');
        $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertOk()
            ->assertJsonPath('data.plans.0.name', 'Autumn')->assertJsonPath('data.plans.0.events.0.id', $event->id);
        $this->putJson("/api/v1/teams/{$team->id}/season-plans/$id", array_merge($payload, ['revision' => 1, 'goals' => 'New goal']))
            ->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson("/api/v1/teams/{$team->id}/season-plans/$id", $payload + ['revision' => 1])->assertConflict();
        $this->assertDatabaseHas('season_plans', ['id' => $id, 'goals' => 'New goal']);
        $this->assertSame($period->id, $event->fresh()->sport_year_period_id);
        $this->deleteJson("/api/v1/teams/{$team->id}/season-plans/$id")->assertOk();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_season_rejects_outsiders_read_only_members_wrong_period_events_and_overlaps(): void
    {
        [$owner, $club, $team, $period] = $this->fixture();
        $member = User::factory()->create();
        $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
        $team->users()->attach($member, ['role' => 'player']);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertOk()->assertJsonPath('data.can_edit', false);
        $this->postJson("/api/v1/teams/{$team->id}/season-plans", $this->season($period))->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/teams/{$team->id}/season-plans")->assertForbidden();
        Sanctum::actingAs($owner);
        $event = $this->event($owner, $club, $team, null);
        Event::whereKey($event->id)->update(['sport_year_period_id' => null]);
        $this->postJson("/api/v1/teams/{$team->id}/season-plans", $this->season($period, [$event->id]))->assertUnprocessable();
        $bad = $this->season($period);
        $bad['phases'][] = $bad['phases'][0];
        $this->postJson("/api/v1/teams/{$team->id}/season-plans", $bad)->assertUnprocessable();
        $club->currentSubscription()->update(['status' => 'cancelled']);
        $this->postJson("/api/v1/teams/{$team->id}/season-plans", $this->season($period))->assertUnprocessable();
    }

    public function test_competition_roster_registration_fixture_results_save_and_reload(): void
    {
        [$owner, $club] = $this->fixture();
        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/v1/clubs/{$club->id}/competitions", [
            'name' => 'Autumn Cup', 'status' => 'open', 'starts_on' => now()->addDays(10)->toDateString(), 'ends_on' => now()->addDays(11)->toDateString(),
        ])->assertCreated()->json('data.id');
        $athlete = User::factory()->create(['athlete_license_number' => 'L1', 'athlete_license_valid_until' => now()->addYear()]);
        $club->users()->attach($athlete, ['role' => 'member', 'membership_status' => 'active']);
        $roster = $this->postJson("/api/v1/competitions/$id/components/roster", ['display_name' => 'Athlete', 'user_id' => $athlete->id])->assertCreated()->json('data.id');
        $second = $this->postJson("/api/v1/competitions/$id/components/roster", ['display_name' => 'Guest'])->assertCreated()->json('data.id');
        $registration = $this->postJson("/api/v1/competitions/$id/registrations", ['roster_ids' => [$roster], 'start_fee_cents' => 0])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/competitions/$id/registrations/$registration/eligibility")->assertOk()->assertJsonPath('data.status', 'pending_confirmation');
        $this->postJson("/api/v1/competitions/$id/registrations/$registration/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/v1/competitions/$id/registrations", ['roster_ids' => [$roster], 'start_fee_cents' => 0])->assertUnprocessable();
        $this->postJson("/api/v1/competitions/$id/tournament", ['roster_ids' => [$roster, $second], 'group_count' => 1])->assertCreated();
        $detail = $this->getJson("/api/v1/competitions/$id")->assertOk();
        $match = collect($detail->json('data.tournament_matches'))->firstWhere('phase', 'group')['id'];
        $this->putJson("/api/v1/competitions/$id/matches/$match", ['home_score' => 2, 'away_score' => 1, 'status' => 'completed', 'reason' => 'Official result'])->assertOk();
        $this->getJson("/api/v1/competitions/$id")->assertOk()->assertJsonPath('data.registrations.0.status', 'confirmed')
            ->assertJsonCount(2, 'data.standings');
        $this->assertDatabaseHas('competition_tournament_matches', ['id' => $match, 'home_score' => 2]);
        $this->postJson("/api/v1/competitions/$id/tournament", ['roster_ids' => [$roster, $second], 'group_count' => 1])->assertUnprocessable();
    }

    public function test_competition_venue_saves_address_contact_access_and_notes(): void
    {
        [$owner, $club] = $this->fixture();
        $competition = Competition::create(['club_id' => $club->id, 'name' => 'Venue Cup', 'status' => 'open']);
        Sanctum::actingAs($owner);

        $venue = $this->postJson("/api/v1/competitions/{$competition->id}/components/venues", [
            'name' => 'Sportpark Mitte',
            'venue_type' => 'Stadion',
            'street' => 'Hauptstraße',
            'house_number' => '12 A',
            'postal_code' => '66111',
            'city' => 'Saarbrücken',
            'country' => 'Deutschland',
            'venue_contact_name' => 'Alex Platzwart',
            'venue_contact_phone' => '+49 681 123456',
            'website' => 'https://example.test/sportpark',
            'access_notes' => 'Mannschaftsbus über Tor 2. Parkplätze hinter der Halle.',
            'venue_notes' => 'Kunstrasen. Stollenschuhe sind nicht erlaubt.',
        ])->assertCreated()
            ->assertJsonPath('data.city', 'Saarbrücken')
            ->assertJsonPath('data.metadata.venue_type', 'Stadion')
            ->assertJsonPath('data.metadata.venue_notes', 'Kunstrasen. Stollenschuhe sind nicht erlaubt.')
            ->json('data');

        $this->getJson("/api/v1/competitions/{$competition->id}")
            ->assertOk()
            ->assertJsonPath('data.venues.0.id', $venue['id'])
            ->assertJsonPath('data.venues.0.street', 'Hauptstraße')
            ->assertJsonPath('data.venues.0.metadata.access_notes', 'Mannschaftsbus über Tor 2. Parkplätze hinter der Halle.');
    }

    public function test_competition_club_boundaries_and_finance_permissions(): void
    {
        [$owner, $club] = $this->fixture();
        $competition = Competition::create(['club_id' => $club->id, 'name' => 'Cup']);
        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/competitions/{$competition->id}")->assertForbidden();
        $this->postJson("/api/v1/competitions/{$competition->id}/components/roster", ['display_name' => 'Invalid'])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/competitions/{$competition->id}/components/roster", ['display_name' => 'Invalid', 'user_id' => $outsider->id])->assertUnprocessable();
        $foreignTeam = Team::factory()->create(['club_id' => Club::factory()->create(['owner_id' => $outsider->id])->id]);
        $this->postJson("/api/v1/clubs/{$club->id}/competitions", ['name' => 'Cup', 'status' => 'open', 'team_id' => $foreignTeam->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02'])->assertUnprocessable();
    }

    public function test_family_appointments_and_child_response_reload_and_revoke_isolate_access(): void
    {
        [$guardian, $club, $team, $period] = $this->fixture();
        $child = User::factory()->create(['birth_date' => now()->subYears(10), 'guardian_consent_at' => now()]);
        $club->users()->attach($child, ['role' => 'member', 'membership_status' => 'active']);
        $team->users()->attach($child, ['role' => 'player']);
        $link = GuardianChildRelationship::create(['club_id' => $club->id, 'child_user_id' => $child->id, 'guardian_user_id' => $guardian->id, 'status' => 'accepted']);
        $event = $this->event($guardian, $club, $team, $period);
        Sanctum::actingAs($guardian);
        $entry = $this->postJson('/api/v1/family-calendar/entries', ['title' => 'Pickup', 'starts_at' => now()->toISOString(), 'ends_at' => now()->addHour()->toISOString(), 'child_user_id' => $child->id, 'club_id' => $club->id])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/family-calendar/events/{$event->id}/people/{$child->id}/response", ['status' => 'yes'])->assertOk()->assertJsonPath('data.response_status', 'yes');
        $this->getJson('/api/v1/family-calendar')->assertOk()->assertJsonPath('data.entries.0.title', 'Pickup');
        $this->assertDatabaseHas('event_participants', ['event_id' => $event->id, 'user_id' => $child->id, 'status' => 'yes', 'response_mode' => 'family']);
        $this->assertDatabaseHas('event_attendance_corrections', ['event_id' => $event->id, 'user_id' => $child->id, 'actor_id' => $guardian->id, 'source' => 'family']);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/family-calendar')->assertOk()->assertJsonCount(0, 'data.entries')->assertJsonCount(0, 'data.children');
        $this->deleteJson("/api/v1/family-calendar/entries/$entry")->assertNotFound();
        Sanctum::actingAs($guardian);
        $link->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->getJson('/api/v1/family-calendar')->assertOk()->assertJsonCount(0, 'data.children')->assertJsonCount(0, 'data.entries');
        $this->postJson("/api/v1/family-calendar/events/{$event->id}/people/{$child->id}/response", ['status' => 'no'])->assertForbidden();
    }

    public function test_family_response_enforces_deadline_capacity_consent_and_relationship_club(): void
    {
        [$guardian, $club, $team, $period] = $this->fixture();
        $child = User::factory()->create(['birth_date' => now()->subYears(10), 'guardian_consent_at' => now()]);
        $club->users()->attach($child, ['role' => 'member', 'membership_status' => 'active']);
        $team->users()->attach($child, ['role' => 'player']);
        GuardianChildRelationship::create(['club_id' => $club->id, 'child_user_id' => $child->id, 'guardian_user_id' => $guardian->id, 'status' => 'accepted']);
        $event = $this->event($guardian, $club, $team, $period);
        $event->update(['participant_response_deadline_at' => now()->subMinute()]);
        Sanctum::actingAs($guardian);
        $url = "/api/v1/family-calendar/events/{$event->id}/people/{$child->id}/response";
        $this->postJson($url, ['status' => 'yes'])->assertUnprocessable();
        $event->update(['participant_response_deadline_at' => null, 'participation_consent_required' => true, 'participation_consent_version' => 'v1']);
        $this->postJson($url, ['status' => 'yes'])->assertUnprocessable();
        $this->postJson($url, ['status' => 'yes', 'consent_accepted' => true, 'consent_version' => 'v1'])->assertOk();
        $child->update(['guardian_consent_revoked_at' => now()]);
        $this->postJson($url, ['status' => 'no'])->assertForbidden();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $period = ClubYearPeriod::create(['club_id' => $club->id, 'type' => 'sport', 'name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ClubSubscription::updateOrCreate(['club_id' => $club->id], ['subscription_plan_id' => SubscriptionPlan::where('slug', 'pro')->firstOrFail()->id, 'status' => 'active']);

        return [$owner, $club, $team, $period];
    }

    private function season(ClubYearPeriod $period, array $events = []): array
    {
        return ['name' => 'Autumn', 'sport_year_period_id' => $period->id, 'status' => 'draft', 'goals' => 'Fitness',
            'phases' => [['name' => 'Preparation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'goal' => 'Endurance']], 'event_ids' => $events];
    }

    private function event(User $owner, Club $club, Team $team, ?ClubYearPeriod $period): Event
    {
        return Event::create(['user_id' => $owner->id, 'club_id' => $club->id, 'team_id' => $team->id,
            'sport_year_period_id' => $period?->id, 'title' => 'Training', 'type' => 'training', 'visibility' => 'private', 'status' => 'scheduled', 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour()]);
    }
}
