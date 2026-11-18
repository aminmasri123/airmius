<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\GamificationXpEvent;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\ApiUserPayloadLoader;
use App\Services\GamificationService;
use App\Support\ClubPermissions;
use App\Support\Performance\ResourceReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileReadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_list_query_count_stays_bounded_as_conversations_grow(): void
    {
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);
        $this->addConversations($viewer, 1);
        [$small] = $this->measure('/api/v1/chat/conversations');
        $this->addConversations($viewer, 7);
        [$large, $largeQueries] = $this->measure('/api/v1/chat/conversations');
        fwrite(STDERR, "\nchat queries: {$small} -> {$large}\n");
        $this->assertLessThanOrEqual($small + 15, $large, $this->topQueries($largeQueries));
    }

    public function test_me_does_not_repeat_club_permission_queries_for_every_capability(): void
    {
        $role = Role::findOrCreate('player', 'web');
        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($viewer->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($viewer);
        [$count, $queries] = $this->measure('/api/v1/me');
        fwrite(STDERR, "\nme with one club queries: {$count}\n");
        $this->assertLessThanOrEqual(80, $count, $this->topQueries($queries));
    }

    public function test_batched_user_payloads_match_individual_totals_and_keep_each_users_latest_twelve_badges(): void
    {
        $users = User::factory()->count(2)->create();
        $users[1]->assignRole(Role::findOrCreate('coach', 'web'));
        foreach ($users as $user) {
            foreach (['sportler', 'trainer'] as $actorType) {
                foreach ([null, User::class] as $ownerType) {
                    GamificationXpEvent::create([
                        'user_id' => $user->id, 'owner_type' => $ownerType,
                        'owner_id' => $ownerType ? $user->id : null,
                        'actor_type' => $actorType, 'reason' => 'fixture', 'amount' => 7,
                        'created_at' => now()->subDay(),
                    ]);
                }
                GamificationXpEvent::create([
                    'user_id' => $user->id, 'owner_type' => User::class, 'owner_id' => $user->id,
                    'actor_type' => $actorType, 'reason' => 'fixture-today', 'amount' => 11,
                ]);
            }
            for ($i = 0; $i < 14; $i++) {
                $badge = Badge::create(['name' => 'Badge '.$i, 'key' => 'b-'.$user->id.'-'.$i]);
                UserBadge::create(['user_id' => $user->id, 'badge_id' => $badge->id, 'awardable_type' => User::class, 'awardable_id' => $user->id]);
            }
        }
        $service = app(GamificationService::class);
        $expected = $users->mapWithKeys(fn ($user) => [$user->id => $service->summaryFor($user)])->all();
        $copies = $users->map(fn ($user) => $user->fresh());
        app(ApiUserPayloadLoader::class)->load($users->concat($copies));
        foreach ($users->concat($copies) as $user) {
            $this->assertSame($expected[$user->id], $user->getRelation('apiGamificationSummary'));
            $this->assertCount(12, $user->profileBadgeAwards);
            $this->assertSame(UserBadge::where('awardable_type', User::class)->where('awardable_id', $user->id)->latest('id')->limit(12)->pluck('id')->all(), $user->profileBadgeAwards->pluck('id')->all());
        }
    }

    public function test_permission_read_cache_is_cleared_before_the_next_request_and_outside_serialization(): void
    {
        $viewer = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($viewer->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.clubs.0.can_edit_metadata', false);
        $this->assertFalse(request()->attributes->has(ResourceReadCache::class));
        $club->users()->updateExistingPivot($viewer->id, ['permission_overrides' => [ClubPermissions::METADATA_EDIT => true]]);
        $this->assertTrue(ClubPermissions::allows($club, $viewer, ClubPermissions::METADATA_EDIT));
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.clubs.0.can_edit_metadata', true);
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->assertFalse(ClubPermissions::allows($club, $other, ClubPermissions::METADATA_EDIT));
    }

    public function test_group_list_keeps_viewer_roles_permissions_and_visible_messages(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::create([
            'type' => 'group', 'owner_id' => $owner->id,
            'posting_policy' => Conversation::POSTING_MANAGEMENT,
        ]);
        foreach ([$owner, $moderator, $member] as $user) {
            $role = $user->is($moderator) ? Conversation::ROLE_MODERATOR : Conversation::ROLE_MEMBER;
            $conversation->users()->attach($user->id, ['role' => $role, 'joined_at' => now()]);
        }
        Message::create(['conversation_id' => $conversation->id, 'sender_id' => $owner->id,
            'message' => 'Old private history', 'status' => 'sent', 'moderation_status' => 'clean',
            'created_at' => now()->subDay()]);
        Message::create(['conversation_id' => $conversation->id, 'sender_id' => $moderator->id,
            'message' => 'Visible', 'status' => 'sent', 'moderation_status' => 'clean']);
        foreach ([[$owner, Conversation::ROLE_OWNER, true, true], [$moderator, Conversation::ROLE_MODERATOR, false, true], [$member, Conversation::ROLE_MEMBER, false, false]] as [$viewer, $role, $isOwner, $canManage]) {
            Sanctum::actingAs($viewer);
            $this->getJson('/api/v1/chat/conversations')->assertOk()
                ->assertJsonPath('data.0.viewer_role', $role)
                ->assertJsonPath('data.0.permissions.can_manage_roles', $isOwner)
                ->assertJsonPath('data.0.permissions.can_manage_members', $canManage)
                ->assertJsonPath('data.0.permissions.can_send_messages', $canManage)
                ->assertJsonPath('data.0.latest_message.message', 'Visible');
        }
        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
    }

    private function addConversations(User $viewer, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $peer = User::factory()->create();
            $conversation = Conversation::create(['type' => 'direct']);
            $conversation->users()->attach([$viewer->id, $peer->id]);
            Message::create(['conversation_id' => $conversation->id, 'sender_id' => $peer->id, 'message' => 'Hello', 'status' => 'sent', 'moderation_status' => 'clean']);
        }
    }

    private function measure(string $url): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($url)->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return [count($queries), $queries];
    }

    private function topQueries(array $queries): string
    {
        return collect($queries)->countBy('query')->sortDesc()->take(6)->toJson();
    }
}
