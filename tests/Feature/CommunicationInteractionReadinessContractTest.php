<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Friendship;
use App\Models\Team;
use App\Models\User;
use App\Support\CommunicationInteractionReadinessRegistry;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationInteractionReadinessContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_covers_t037c_surface_without_personal_data_or_closed_gates(): void
    {
        $registry = CommunicationInteractionReadinessRegistry::definitions();
        $encoded = json_encode($registry, JSON_THROW_ON_ERROR);

        $this->assertSame('communication-interaction-readiness.v1', $registry['contract']);
        $this->assertSame('T037c', $registry['task']);
        $this->assertSame('local-contract-ready-external-gates-open', $registry['decision']);
        $this->assertTrue($registry['data_policy']['registry_contains_personal_data'] === false);
        $this->assertTrue($registry['data_policy']['external_legal_retention_approval_required']);

        foreach ([
            'direct_chat',
            'group_chat',
            'chat_attachments',
            'club_surveys',
            'event_decisions',
            'required_confirmations',
        ] as $capability) {
            $this->assertContains($capability, $registry['scope']);
            $this->assertArrayHasKey($capability, $registry['capabilities']);
            $this->assertNotEmpty($registry['capabilities'][$capability]['routes']);
            $this->assertNotEmpty($registry['capabilities'][$capability]['moderation_rules']);
            $this->assertNotEmpty($registry['capabilities'][$capability]['retention_rules']);
        }

        foreach ($registry['capabilities'] as $capability) {
            foreach ($capability['artifacts'] as $path) {
                $this->assertFileExists(base_path($path), "{$path} fehlt.");
            }
        }

        foreach ([
            'api.v1.chat.conversations.store',
            'api.v1.chat.messages.store',
            'api.v1.clubs.surveys.vote',
            'api.v1.events.decisions.vote',
            'api.v1.clubs.policy-documents.index',
        ] as $routeName) {
            $this->assertTrue(app('router')->has($routeName), "{$routeName} fehlt.");
        }

        $this->assertStringNotContainsString('@example.', $encoded);
        $this->assertStringNotContainsString('recipient_ids', $encoded);
        $this->assertStringNotContainsString('users/', $encoded);
    }

    public function test_minor_safety_and_club_scope_are_fail_closed_for_t037c_chat_creation(): void
    {
        Role::findOrCreate('minor_pending_consent', 'web');

        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $secondFriend = User::factory()->create();
        $minor = User::factory()->create(['birth_date' => now()->subYears(13)->toDateString()]);
        $club = Club::factory()->create(['owner_id' => $actor->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $this->befriend($actor, $friend);
        $this->befriend($actor, $secondFriend);

        $club->users()->syncWithoutDetaching([
            $actor->id => ['role' => 'member', 'membership_status' => 'active'],
            $friend->id => ['role' => 'member', 'membership_status' => 'active'],
            $secondFriend->id => ['role' => 'member', 'membership_status' => 'active'],
            $minor->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $foreignClub->users()->syncWithoutDetaching([
            $actor->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team->users()->attach([
            $actor->id => ['role' => TeamRoles::COACH],
            $minor->id => ['role' => TeamRoles::PLAYER],
        ]);
        $minor->assignRole('minor_pending_consent');

        Sanctum::actingAs($actor);
        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'club_id' => $foreignClub->id,
            'participant_ids' => [$friend->id, $secondFriend->id],
            'name' => 'Falscher Vereinskontext',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('conversations', [
            'club_id' => $foreignClub->id,
            'name' => 'Falscher Vereinskontext',
        ]);

        Sanctum::actingAs($minor);
        $this->getJson('/api/v1/chat/conversations')->assertForbidden();
        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'team',
            'team_id' => $team->id,
            'message' => 'Bitte Termin bestaetigen.',
        ])->assertForbidden();

        $minor->removeRole('minor_pending_consent');
        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'team',
            'team_id' => $team->id,
            'message' => 'Bitte Termin bestaetigen.',
        ])->assertCreated()->assertJsonPath('data.team.id', $team->id);
    }

    private function befriend(User $first, User $second): void
    {
        Friendship::query()->create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friendship::query()->create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
