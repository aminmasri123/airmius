<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Friendship;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChallengeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_friends_can_send_accept_complete_and_comment_on_a_daily_challenge(): void
    {
        $creator = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::query()->create(['user_id' => $creator->id, 'friend_id' => $friend->id]);
        Friendship::query()->create(['user_id' => $friend->id, 'friend_id' => $creator->id]);
        $sport = Sport::query()->create(['name' => 'Gehen', 'slug' => 'walking', 'category' => 'endurance', 'is_active' => true]);

        Sanctum::actingAs($creator);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Jeden Tag 10.000 Schritte',
            'description' => 'Gemeinsam bleiben wir in Bewegung.',
            'sport_id' => $sport->id,
            'visibility' => 'invite_only',
            'metric' => 'steps',
            'target_value' => 10000,
            'unit' => 'Schritte',
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(6)->toDateString(),
            'invitee_ids' => [$friend->id],
        ])->assertCreated()
            ->assertJsonPath('data.my_participation.status', 'accepted')
            ->assertJsonPath('data.participants.1.status', 'pending')
            ->json('data.id');

        Sanctum::actingAs($friend);
        $this->getJson('/api/v1/challenges')->assertOk()->assertJsonPath('data.0.id', $challengeId);
        $this->putJson("/api/v1/challenges/{$challengeId}/invitation", ['status' => 'accepted'])
            ->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->putJson("/api/v1/challenges/{$challengeId}/check-ins/".today()->toDateString(), [
            'value' => 10250,
            'note' => 'Nach dem Spaziergang geschafft.',
        ])->assertOk()->assertJsonPath('data.completed', true);
        $this->postJson("/api/v1/challenges/{$challengeId}/comments", ['content' => 'Tag eins ist geschafft!'])
            ->assertCreated()->assertJsonPath('data.content', 'Tag eins ist geschafft!');

        $this->getJson("/api/v1/challenges/{$challengeId}")
            ->assertOk()
            ->assertJsonPath('data.progress.completed', 1)
            ->assertJsonPath('data.my_checkins.0.value', 10250)
            ->assertJsonPath('data.comments.0.content', 'Tag eins ist geschafft!');
    }

    public function test_private_challenges_are_not_visible_to_uninvited_users(): void
    {
        $creator = User::factory()->create();
        $stranger = User::factory()->create();
        Sanctum::actingAs($creator);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Private Challenge',
            'visibility' => 'invite_only',
            'metric' => 'sessions',
            'target_value' => 1,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/challenges/{$challengeId}")->assertNotFound();
        $this->getJson('/api/v1/challenges')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_daily_challenge_can_require_separate_morning_and_evening_confirmations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Morgens und abends Liegestütze',
            'visibility' => 'invite_only',
            'metric' => 'repetitions',
            'target_value' => 20,
            'unit' => 'Liegestütze',
            'frequency' => 'daily',
            'checkin_slots' => ['morning', 'evening'],
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.checkin_slots.0', 'morning')
            ->assertJsonPath('data.checkin_slots.1', 'evening')
            ->assertJsonPath('data.progress.total', 2)
            ->json('data.id');

        $this->putJson("/api/v1/challenges/{$challengeId}/check-ins/".today()->toDateString(), [
            'slot' => 'morning',
            'value' => 20,
        ])->assertOk()->assertJsonPath('data.slot', 'morning')->assertJsonPath('data.completed', true);

        $this->putJson("/api/v1/challenges/{$challengeId}/check-ins/".today()->toDateString(), [
            'slot' => 'evening',
            'value' => 20,
        ])->assertOk()->assertJsonPath('data.slot', 'evening')->assertJsonPath('data.completed', true);

        $this->getJson("/api/v1/challenges/{$challengeId}")
            ->assertOk()
            ->assertJsonPath('data.progress.completed', 2)
            ->assertJsonCount(2, 'data.my_checkins');

        foreach ([[], ['slot' => null], ['slot' => ''], ['slot' => 'anytime']] as $payload) {
            $this->putJson("/api/v1/challenges/{$challengeId}/check-ins/".today()->toDateString(), $payload + ['value' => 20])
                ->assertUnprocessable()->assertJsonValidationErrors('slot');
        }

        $this->travel(1)->days();
        $this->getJson("/api/v1/challenges/{$challengeId}")
            ->assertOk()
            ->assertJsonPath('data.progress.completed', 2)
            ->assertJsonPath('data.progress.total', 4)
            ->assertJsonCount(2, 'data.my_checkins');
        $this->putJson("/api/v1/challenges/{$challengeId}/check-ins/".today()->toDateString(), [
            'slot' => 'morning', 'value' => 20,
        ])->assertOk()->assertJsonPath('data.completed', true);
        $this->getJson("/api/v1/challenges/{$challengeId}")
            ->assertOk()->assertJsonPath('data.progress.completed', 3)
            ->assertJsonCount(3, 'data.my_checkins');
        $this->travelBack();
    }

    public function test_regular_users_cannot_publish_public_challenges(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/challenges', [
            'title' => 'Public Challenge',
            'visibility' => 'public',
            'metric' => 'steps',
            'target_value' => 10000,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addWeek()->toDateString(),
        ])->assertForbidden();
    }

    public function test_admin_can_publish_a_public_challenge_that_any_user_can_join(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin')->users()->attach($admin);
        $member = User::factory()->create();
        Sanctum::actingAs($admin);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Airmius Schritte-Woche',
            'visibility' => 'public',
            'metric' => 'steps',
            'target_value' => 10000,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addWeek()->toDateString(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/challenges')->assertOk()->assertJsonPath('data.0.can_join', true);
        $this->postJson("/api/v1/challenges/{$challengeId}/join")
            ->assertOk()->assertJsonPath('data.status', 'accepted');
    }

    public function test_club_challenges_are_only_visible_inside_the_club(): void
    {
        $trainer = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $trainer->id]);
        $club->users()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($trainer);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Interne Vereins-Challenge',
            'visibility' => 'club',
            'club_id' => $club->id,
            'metric' => 'duration_minutes',
            'target_value' => 30,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/challenges/{$challengeId}")->assertOk();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/challenges/{$challengeId}")->assertNotFound();
    }

    public function test_scoped_event_right_controls_challenge_creation_without_global_coach_bypass(): void
    {
        $owner = User::factory()->create();
        $specialist = User::factory()->create();
        $deniedManager = User::factory()->create();
        $directCoach = User::factory()->create();
        $deniedDirectCoach = User::factory()->create();
        $foreignCoach = User::factory()->create();
        Role::findOrCreate('coach')->users()->attach($foreignCoach);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach($specialist->id, ['role' => 'member', 'membership_status' => 'active']);
        $team->users()->attach($specialist->id, ['role' => TeamRoles::PLAYER]);
        $club->users()->attach($deniedManager->id, [
            'role' => 'manager',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::EVENTS_EDIT => false],
        ]);
        $club->users()->attach($deniedDirectCoach->id, [
            'role' => 'member',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::EVENTS_EDIT => false],
        ]);
        $team->users()->attach($directCoach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($deniedDirectCoach->id, ['role' => TeamRoles::COACH]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'challenge_editor',
            'name' => 'Challenge editor',
            'permissions' => [ClubPermissions::EVENTS_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $specialist->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
        $payload = fn (string $visibility, array $scope) => [
            'title' => 'Scoped Challenge',
            'visibility' => $visibility,
            ...$scope,
            'metric' => 'sessions',
            'target_value' => 3,
            'frequency' => 'weekly',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addWeek()->toDateString(),
        ];

        Sanctum::actingAs($specialist);
        $this->postJson('/api/v1/challenges', $payload('club', ['club_id' => $club->id]))
            ->assertCreated()
            ->assertJsonPath('data.club.id', $club->id);
        $this->postJson('/api/v1/challenges', $payload('team', ['team_id' => $team->id]))
            ->assertCreated()
            ->assertJsonPath('data.team.id', $team->id);

        Sanctum::actingAs($deniedManager);
        $this->postJson('/api/v1/challenges', $payload('club', ['club_id' => $club->id]))
            ->assertForbidden();

        Sanctum::actingAs($foreignCoach);
        $this->postJson('/api/v1/challenges', $payload('team', ['team_id' => $team->id]))
            ->assertForbidden();

        Sanctum::actingAs($directCoach);
        $directCoachChallengeId = $this->postJson('/api/v1/challenges', $payload('team', ['team_id' => $team->id]))
            ->assertCreated()
            ->assertJsonPath('data.can_cancel', true)
            ->json('data.id');

        $club->users()->attach($directCoach->id, [
            'role' => 'member',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::EVENTS_EDIT => false],
        ]);
        $this->getJson("/api/v1/challenges/{$directCoachChallengeId}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false);
        $this->postJson("/api/v1/challenges/{$directCoachChallengeId}/cancel")
            ->assertForbidden();

        Sanctum::actingAs($specialist);
        $this->getJson("/api/v1/challenges/{$directCoachChallengeId}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true);
        $this->postJson("/api/v1/challenges/{$directCoachChallengeId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        Sanctum::actingAs($deniedDirectCoach);
        $this->postJson('/api/v1/challenges', $payload('team', ['team_id' => $team->id]))
            ->assertForbidden();
    }
}
