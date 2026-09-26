<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventVisibilityContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_event_visibility_contract_separates_public_club_team_personal_calendar_and_attendance_surfaces(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');

        $owner = User::factory()->create();
        $clubMember = User::factory()->create();
        $teamMember = User::factory()->create();
        $outsider = User::factory()->create();
        $personalOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);

        $club->users()->attach($clubMember->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($teamMember->id, ['role' => 'member', 'membership_status' => 'active']);
        $team->users()->attach($teamMember->id, ['role' => TeamRoles::PLAYER]);

        $this->event('Public Open Run', $owner, 'public', now()->addDay());
        $clubEvent = $this->event('Club Assembly', $owner, 'organization', now()->addDays(2), $club);
        $teamEvent = $this->event('Team Practice', $owner, 'private', now()->addDays(3), $club, $team);
        $this->event('Other Team Practice', $owner, 'private', now()->addDays(4), $club, $otherTeam);
        $personal = $this->event('Personal Recovery Block', $personalOwner, 'private', now()->addDays(5));

        EventParticipant::query()->create([
            'event_id' => $teamEvent->id,
            'user_id' => $outsider->id,
            'status' => 'yes',
            'rsvp_status' => 'yes',
            'attendance_status' => 'present',
            'response_mode' => 'qr',
            'responded_at' => now(),
            'checked_in_at' => now()->addMinutes(5),
            'check_in_method' => 'qr',
        ]);

        $this->assertVisibleTitles($outsider, [
            'Public Open Run',
            'Team Practice',
        ]);
        $this->assertVisibleTitles($clubMember, [
            'Public Open Run',
            'Club Assembly',
        ]);
        $this->assertVisibleTitles($teamMember, [
            'Public Open Run',
            'Club Assembly',
            'Team Practice',
        ]);
        $this->assertVisibleTitles($personalOwner, [
            'Public Open Run',
            'Personal Recovery Block',
        ]);

        Sanctum::actingAs($clubMember);

        $this->getJson('/api/v1/events?calendar_month=2026-09&period=all')
            ->assertOk()
            ->assertJsonPath('calendar.month', '2026-09')
            ->assertJsonFragment(['title' => 'Club Assembly'])
            ->assertJsonMissing(['title' => 'Team Practice'])
            ->assertJsonMissing(['title' => 'Other Team Practice'])
            ->assertJsonMissing(['title' => 'Personal Recovery Block']);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/events/'.$teamEvent->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Team Practice')
            ->assertJsonPath('data.my_participation_status', 'yes')
            ->assertJsonPath('data.participants.0.pivot.check_in_method', 'qr')
            ->assertJsonPath('data.can_manage_attendance', false);

        $this->getJson('/api/v1/events/'.$clubEvent->id)->assertNotFound();
        $this->getJson('/api/v1/events/'.$personal->id)->assertNotFound();
    }

    private function event(
        string $title,
        User $owner,
        string $visibility,
        Carbon $startsAt,
        ?Club $club = null,
        ?Team $team = null,
    ): Event {
        return Event::query()->create([
            'club_id' => $club?->id,
            'team_id' => $team?->id,
            'user_id' => $owner->id,
            'title' => $title,
            'type' => $visibility === 'public' ? 'public' : 'training',
            'visibility' => $visibility,
            'status' => 'scheduled',
            'start_time' => $startsAt,
            'event_timezone' => 'Europe/Berlin',
            'recurring' => $title === 'Team Practice' ? 'weekly' : null,
            'recurrence_days' => $title === 'Team Practice' ? [6] : null,
            'recurrence_ends_at' => $title === 'Team Practice' ? $startsAt->copy()->addWeeks(2) : null,
        ]);
    }

    private function assertVisibleTitles(User $user, array $expectedTitles): void
    {
        $this->assertSame(
            $expectedTitles,
            Event::query()
                ->visibleTo($user)
                ->orderBy('start_time')
                ->pluck('title')
                ->all(),
        );
    }
}
