<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventCreationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_event_editor_can_create_recurring_club_events_after_the_personal_free_limit(): void
    {
        [$club, $owner, $editor] = $this->clubFixture();
        $this->assign($club, $owner, $editor, 'club', null);
        $this->consumeFreeLimit($editor);

        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonPath('event_creation.allows_recurring', true)
            ->assertJsonPath('event_creation.allows_recurring_globally', false)
            ->assertJsonPath('event_creation.recurring_club_ids.0', $club->id);

        $this->postJson('/api/v1/events', [
            'club_id' => $club->id,
            'title' => 'Vereinsserie',
            'type' => 'meeting',
            'visibility' => 'organization',
            'start_time' => now()->addDay()->toISOString(),
            'recurring' => 'weekly',
            'recurrence_days' => [(int) now()->addDay()->dayOfWeek],
            'recurrence_ends_at' => now()->addWeeks(3)->toISOString(),
        ])->assertOk();

        $this->assertDatabaseHas('events', [
            'club_id' => $club->id,
            'user_id' => $editor->id,
            'title' => 'Vereinsserie',
        ]);

        $this->postJson('/api/v1/events', [
            'title' => 'Öffentliche Serie',
            'type' => 'meeting',
            'visibility' => 'public',
            'start_time' => now()->addDay()->toISOString(),
            'recurring' => 'weekly',
            'recurrence_days' => [(int) now()->addDay()->dayOfWeek],
            'recurrence_ends_at' => now()->addWeeks(3)->toISOString(),
        ])->assertForbidden();
    }

    public function test_team_scoped_event_editor_can_only_create_for_the_assigned_team(): void
    {
        [$club, $owner, $editor] = $this->clubFixture();
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);
        $this->assign($club, $owner, $editor, 'team', $team->id);
        $this->consumeFreeLimit($editor);

        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(1, 'teams')
            ->assertJsonPath('teams.0.id', $team->id)
            ->assertJsonPath('teams.0.club_id', $club->id)
            ->assertJsonPath('event_creation.recurring_team_ids.0', $team->id);

        $payload = [
            'title' => 'Mannschaftsserie',
            'type' => 'training',
            'visibility' => 'private',
            'start_time' => now()->addDay()->toISOString(),
            'recurring' => 'weekly',
            'recurrence_days' => [(int) now()->addDay()->dayOfWeek],
            'recurrence_ends_at' => now()->addWeeks(3)->toISOString(),
        ];

        $this->postJson('/api/v1/events', [...$payload, 'team_id' => $team->id])
            ->assertOk();
        $this->postJson('/api/v1/events', [...$payload, 'team_id' => $otherTeam->id])
            ->assertNotFound();

        $this->assertDatabaseHas('events', [
            'team_id' => $team->id,
            'user_id' => $editor->id,
            'title' => 'Mannschaftsserie',
        ]);
        $this->assertDatabaseMissing('events', [
            'team_id' => $otherTeam->id,
            'user_id' => $editor->id,
            'title' => 'Mannschaftsserie',
        ]);
    }

    public function test_web_creation_uses_the_same_club_scoped_entitlement(): void
    {
        [$club, $owner, $editor] = $this->clubFixture();
        $this->assign($club, $owner, $editor, 'club', null);
        $this->consumeFreeLimit($editor);

        $this->actingAs($editor)
            ->post(route('auth.events.store'), [
                'club_id' => $club->id,
                'title' => 'Web-Vereinsserie',
                'type' => 'meeting',
                'visibility' => 'organization',
                'start_time' => now()->addDay()->toDateTimeString(),
                'recurring' => 'weekly',
                'recurrence_days' => [(int) now()->addDay()->dayOfWeek],
                'recurrence_ends_at' => now()->addWeeks(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'club_id' => $club->id,
            'user_id' => $editor->id,
            'title' => 'Web-Vereinsserie',
        ]);
    }

    /** @return array{Club, User, User} */
    private function clubFixture(): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($editor->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        return [$club, $owner, $editor];
    }

    private function assign(Club $club, User $owner, User $editor, string $scopeType, ?int $scopeId): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'event_editor_'.$scopeType,
            'name' => 'Terminredaktion',
            'permissions' => [ClubPermissions::EVENTS_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $editor->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'scope_key' => $scopeType.($scopeId ? ':'.$scopeId : ''),
            'assigned_by' => $owner->id,
        ]);
    }

    private function consumeFreeLimit(User $user): void
    {
        foreach ([1, 2] as $index) {
            Event::query()->create([
                'user_id' => $user->id,
                'title' => 'Privattermin '.$index,
                'type' => 'training',
                'visibility' => 'public',
                'status' => 'scheduled',
                'start_time' => now()->addDays($index),
            ]);
        }
    }
}
