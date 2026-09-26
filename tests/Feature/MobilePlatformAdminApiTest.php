<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Club;
use App\Models\ContentReport;
use App\Models\GamificationRule;
use App\Models\ModerationFlag;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\Sport;
use App\Models\User;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobilePlatformAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_manage_core_platform_catalogs_and_reviews(): void
    {
        Notification::fake();
        $admin = $this->systemAdmin(twoFactor: true);
        Sanctum::actingAs($admin);

        $target = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $target->id,
            'verification_status' => 'pending',
            'requested_official_club_number' => 'VR-2026-41',
        ]);
        $sport = Sport::query()->create([
            'name' => 'Laufen',
            'slug' => 'laufen',
            'category' => 'Ausdauer',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $badge = Badge::query()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'actor_type' => 'sportler',
            'trigger' => 'xp',
            'threshold' => 10,
        ]);

        $this->getJson('/api/v1/admin/platform')
            ->assertOk()
            ->assertJsonPath('data.summary.users', 2)
            ->assertJsonPath('data.summary.clubs_pending', 1)
            ->assertJsonPath('data.users.0.can_change_status', true)
            ->assertJsonFragment(['name' => 'Laufen'])
            ->assertJsonFragment(['key' => 'starter']);

        $this->patchJson("/api/v1/admin/platform/users/{$target->id}/status", [
            'action' => 'suspend',
            'days' => 7,
            'reason' => 'Sicherheitsprüfung',
        ])
            ->assertOk()
            ->assertJsonPath('data.account_status', 'suspended');
        $this->assertSame('suspended', $target->fresh()->account_status);

        $this->patchJson("/api/v1/admin/platform/users/{$target->id}/status", [
            'action' => 'lift',
        ])
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active');

        $this->patchJson("/api/v1/admin/platform/clubs/{$club->id}/approve", [
            'mark_official' => true,
            'official_club_number' => 'VR-2026-41',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');
        $this->assertTrue((bool) $club->fresh()->is_official);

        $createdSportId = $this->postJson('/api/v1/admin/platform/sports', [
            'name' => 'Padel',
            'category' => 'Rückschlag',
            'sort_order' => 20,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'padel')
            ->json('data.id');

        $this->patchJson("/api/v1/admin/platform/sports/{$createdSportId}", [
            'name' => 'Padel',
            'slug' => 'padel',
            'category' => 'Rückschlag',
            'sort_order' => 21,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/admin/platform/sports/{$createdSportId}", [
            'confirmation' => 'delete',
        ])->assertOk()->assertJsonPath('data.deleted', true);

        $createdBadgeId = $this->postJson('/api/v1/admin/platform/badges', [
            'key' => 'fair_play',
            'name' => 'Fair Play',
            'actor_type' => 'team',
            'trigger' => 'reason',
            'threshold' => 1,
            'meta' => ['reason' => 'fair_play'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.meta.reason', 'fair_play')
            ->json('data.id');

        $this->patchJson("/api/v1/admin/platform/badges/{$createdBadgeId}", [
            'key' => 'fair_play',
            'name' => 'Fair Play Team',
            'actor_type' => 'team',
            'trigger' => 'reason',
            'threshold' => 1,
            'meta' => ['reason' => 'fair_play'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Fair Play Team');

        $this->deleteJson("/api/v1/admin/platform/badges/{$createdBadgeId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseHas('sports', ['id' => $sport->id]);
        $this->assertDatabaseHas('badges', ['id' => $badge->id]);
    }

    public function test_platform_admin_contract_requires_permission_and_confirmed_two_factor(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/platform')->assertForbidden();

        $admin = $this->systemAdmin(twoFactor: false);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/platform')
            ->assertForbidden()
            ->assertJsonPath('code', AdminTwoFactor::ERROR_CODE);
    }

    public function test_system_admin_can_manage_roles_moderation_and_gamification(): void
    {
        $admin = $this->systemAdmin(twoFactor: true);
        Sanctum::actingAs($admin);
        $member = User::factory()->create();
        $post = Post::factory()->for($member)->create([
            'content' => 'Ein gemeldeter Testbeitrag.',
        ]);
        $flag = ModerationFlag::query()->create([
            'flaggable_type' => Post::class,
            'flaggable_id' => $post->id,
            'user_id' => $member->id,
            'source' => 'automatic',
            'severity' => 'medium',
            'categories' => ['spam'],
            'status' => 'open',
        ]);
        $report = ContentReport::query()->create([
            'reporter_id' => $member->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'details' => 'Bitte durch Moderation prüfen.',
            'status' => 'open',
            'appeal_reason' => 'Die Entscheidung soll erneut geprüft werden.',
            'appeal_status' => 'pending',
            'appealed_at' => now(),
        ]);
        $rule = GamificationRule::query()->create([
            'key' => 'post_created',
            'actor_type' => 'sportler',
            'category' => 'activity',
            'label' => 'Beitrag erstellt',
            'xp_amount' => 5,
            'daily_limit' => 3,
            'trust_delta' => 1,
            'is_penalty' => false,
            'is_active' => true,
        ]);
        Permission::findOrCreate('feed.publish', 'web');

        $this->getJson('/api/v1/admin/platform')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Beitrag erstellt'])
            ->assertJsonFragment(['reason' => 'spam'])
            ->assertJsonPath('data.summary.moderation_open', 2)
            ->assertJsonPath('data.summary.appeals_pending', 1);

        $this->postJson('/api/v1/admin/platform/permissions', [
            'name' => 'mobile.audit',
            'description' => 'Mobile Audit prüfen',
        ])->assertCreated()->assertJsonPath('data.name', 'mobile.audit');

        $roleId = $this->postJson('/api/v1/admin/platform/roles', [
            'name' => 'mobile_moderator',
            'description' => 'Mobile Moderation',
            'permissions' => ['feed.publish'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'mobile_moderator')
            ->json('data.id');

        $this->patchJson("/api/v1/admin/platform/roles/{$roleId}", [
            'description' => 'Moderation und Audit',
            'permissions' => ['feed.publish', 'mobile.audit'],
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Moderation und Audit');

        $this->patchJson("/api/v1/admin/platform/moderation/flags/{$flag->id}", [
            'status' => 'actioned',
            'remove_content' => true,
            'decision_reason' => 'Der Inhalt verletzt die Community-Regeln.',
        ])
            ->assertOk()
            ->assertJsonPath('data.action_taken', 'content_removed');
        $this->assertSame('removed', $post->fresh()->moderation_status);

        $this->patchJson("/api/v1/admin/platform/moderation/reports/{$report->id}", [
            'status' => 'dismissed',
            'decision_reason' => 'Die Erstprüfung ergab keinen weiteren Verstoß.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        $this->patchJson("/api/v1/admin/platform/moderation/reports/{$report->id}/appeal", [
            'appeal_status' => 'accepted',
            'appeal_decision' => 'Die Beschwerde ist begründet und wird erneut geprüft.',
        ])
            ->assertOk()
            ->assertJsonPath('data.appeal_status', 'accepted')
            ->assertJsonPath('data.status', 'open');

        $this->patchJson("/api/v1/admin/platform/gamification-rules/{$rule->id}", [
            'label' => 'Qualitätsbeitrag erstellt',
            'description' => 'Belohnt hilfreiche Beiträge.',
            'xp_amount' => 8,
            'daily_limit' => 2,
            'trust_delta' => 1,
            'is_active' => true,
            'actor_type' => 'sportler',
        ])
            ->assertOk()
            ->assertJsonPath('data.xp_amount', 8);

        $this->deleteJson("/api/v1/admin/platform/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_club_owner_cannot_verify_their_own_club_even_with_system_access(): void
    {
        Notification::fake();
        $ownerAdmin = $this->systemAdmin(twoFactor: true);
        $reviewer = $this->systemAdmin(twoFactor: true);
        $club = Club::factory()->create([
            'owner_id' => $ownerAdmin->id,
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($ownerAdmin);
        $this->patchJson("/api/v1/admin/platform/clubs/{$club->id}/approve", [
            'mark_official' => false,
        ])->assertUnprocessable();
        $this->assertSame('pending', $club->fresh()->verification_status);
        $this->actingAs($ownerAdmin)
            ->put(route('admin.club-verifications.approve', $club), ['mark_official' => false])
            ->assertStatus(422);

        Sanctum::actingAs($reviewer);
        $this->patchJson("/api/v1/admin/platform/clubs/{$club->id}/approve", [
            'mark_official' => false,
        ])->assertOk()->assertJsonPath('data.verification_status', 'verified');
    }

    private function systemAdmin(bool $twoFactor): User
    {
        $permissions = collect([
            'system.manage',
            'users.view',
            'users.edit',
            'users.assign_roles',
        ])
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role = Role::findOrCreate('super_admin', 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create($twoFactor ? [
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ] : []);
        $user->assignRole($role);

        return $user;
    }
}
