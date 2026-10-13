<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\ClubYearPeriod;
use App\Models\Competition;
use App\Models\Event;
use App\Models\FamilyCalendarEntry;
use App\Models\GuardianChildRelationship;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanningWorkflowsTest extends TestCase
{
    use RefreshDatabase;

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
        $club->currentSubscription->update(['status' => 'cancelled']);
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
        $club->users()->attach($child, ['role' => 'member']);
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
