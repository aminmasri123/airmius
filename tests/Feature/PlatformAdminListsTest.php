<?php

namespace Tests\Feature;

use App\Models\AccountWarning;
use App\Models\Club;
use App\Models\ContentReport;
use App\Models\ModerationFlag;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformAdminListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_and_clubs_reach_older_records_and_search_using_web_fields(): void
    {
        $admin = $this->actor('system.manage');
        $older = User::factory()->create(['name' => 'Older member', 'account_status' => 'suspended']);
        User::factory()->count(52)->create();
        $club = Club::factory()->create(['owner_id' => $older->id, 'name' => 'Older club', 'verification_status' => 'pending']);
        Club::factory()->count(102)->create(['owner_id' => $admin->id, 'verification_status' => 'verified']);
        $first = $this->getJson('/api/v1/admin/platform')->assertOk()
            ->assertJsonCount(25, 'data.users')->assertJsonCount(25, 'data.clubs')
            ->assertJsonPath('data.users_meta.total', 54)->assertJsonPath('data.clubs_meta.total', 103)->json('data');
        $last = $this->getJson('/api/v1/admin/platform?users_page=3&clubs_page=5')->assertOk()
            ->assertJsonCount(4, 'data.users')->assertJsonCount(3, 'data.clubs')->json('data');
        $this->assertContains($older->id, array_column($last['users'], 'id'));
        $this->assertContains($club->id, array_column($last['clubs'], 'id'));
        $this->assertEmpty(array_intersect(array_column($first['users'], 'id'), array_column($last['users'], 'id')));
        $this->assertEmpty(array_intersect(array_column($first['clubs'], 'id'), array_column($last['clubs'], 'id')));
        $this->getJson('/api/v1/admin/platform?'.http_build_query([
            'users_q' => $older->email, 'users_status' => 'suspended',
            'clubs_q' => $older->email, 'clubs_status' => 'pending',
        ]))->assertOk()->assertJsonPath('data.users_meta.total', 1)->assertJsonPath('data.users.0.id', $older->id)
            ->assertJsonPath('data.clubs_meta.total', 1)->assertJsonPath('data.clubs.0.id', $club->id)
            ->assertJsonPath('data.summary.users', 54)->assertJsonPath('data.summary.clubs_pending', 1);
    }

    public function test_moderation_pages_categories_and_global_counts_include_records_beyond_legacy_caps(): void
    {
        $moderator = $this->actor('moderation.manage');
        $post = Post::factory()->create(['user_id' => $moderator->id]);
        for ($index = 0; $index < 105; $index++) {
            $flag = ModerationFlag::query()->create([
                'flaggable_type' => Post::class, 'flaggable_id' => $post->id, 'user_id' => $moderator->id,
                'status' => $index < 85 ? 'open' : 'dismissed', 'severity' => 'high',
                'categories' => [$index === 0 ? 'older-category' : 'general'],
            ]);
            ContentReport::query()->create([
                'reportable_type' => Post::class, 'reportable_id' => $post->id, 'reporter_id' => $moderator->id,
                'reason' => 'spam', 'status' => $index < 85 ? 'open' : 'dismissed',
                'appeal_status' => $index === 0 ? 'pending' : null,
            ]);
            AccountWarning::query()->create([
                'user_id' => $moderator->id, 'moderation_flag_id' => $flag->id,
                'severity' => 'high', 'points' => 3, 'reason' => "Warning {$index}",
            ]);
        }
        $first = $this->getJson('/api/v1/admin/platform')->assertOk()
            ->assertJsonPath('data.summary.moderation_open', 170)
            ->assertJsonPath('data.summary.flags_open', 85)->assertJsonPath('data.summary.reports_open', 85)
            ->assertJsonPath('data.summary.appeals_pending', 1)->assertJsonPath('data.summary.warnings_90_days', 105)
            ->assertJsonPath('data.summary.users_with_warnings_90_days', 1)
            ->assertJsonPath('data.moderation.flag_categories', ['general', 'older-category'])
            ->assertJsonPath('data.moderation.warning_categories', ['general', 'older-category'])
            ->assertJsonCount(0, 'data.users')->assertJsonPath('data.users_meta', null)
            ->json('data.moderation');
        $last = $this->getJson('/api/v1/admin/platform?flags_page=5&reports_page=5&warnings_page=5')->assertOk()
            ->assertJsonPath('data.moderation.flags_meta.total', 105)
            ->assertJsonCount(5, 'data.moderation.flags')->assertJsonCount(5, 'data.moderation.reports')
            ->assertJsonCount(5, 'data.moderation.warnings')->json('data.moderation');
        foreach (['flags', 'reports', 'warnings'] as $list) {
            $this->assertEmpty(array_intersect(array_column($first[$list], 'id'), array_column($last[$list], 'id')));
        }
        $this->getJson('/api/v1/admin/platform?flags_category=older-category&flags_status=open&flags_severity=high&reports_appeal=pending&reports_status=open&warnings_category=older-category&warnings_severity=high&warnings_q=Warning')
            ->assertOk()->assertJsonCount(1, 'data.moderation.flags')->assertJsonCount(1, 'data.moderation.reports')
            ->assertJsonCount(1, 'data.moderation.warnings')
            ->assertJsonPath('data.moderation.warnings.0.flag.categories', ['older-category'])
            ->assertJsonPath('data.summary.moderation_open', 170)->assertJsonPath('data.summary.warnings_90_days', 105);
        $this->getJson('/api/v1/admin/platform?flags_category=absent&warnings_category=absent&reports_status=actioned')
            ->assertOk()->assertJsonCount(0, 'data.moderation.flags')->assertJsonCount(0, 'data.moderation.reports')
            ->assertJsonCount(0, 'data.moderation.warnings')->assertJsonPath('data.moderation.warnings_meta.total', 0)
            ->assertJsonPath('data.moderation.warning_categories', ['general', 'older-category']);
    }

    public function test_filtered_last_page_recovers_after_a_moderation_decision(): void
    {
        $moderator = $this->actor('moderation.manage');
        $post = Post::factory()->create(['user_id' => $moderator->id]);
        for ($index = 0; $index < 26; $index++) {
            ModerationFlag::query()->create([
                'flaggable_type' => Post::class, 'flaggable_id' => $post->id,
                'user_id' => $moderator->id, 'status' => 'open',
            ]);
        }
        $id = $this->getJson('/api/v1/admin/platform?flags_status=open&flags_page=2')
            ->assertOk()->assertJsonCount(1, 'data.moderation.flags')->json('data.moderation.flags.0.id');
        $this->patchJson("/api/v1/admin/platform/moderation/flags/{$id}", ['status' => 'dismissed'])->assertOk();
        $this->getJson('/api/v1/admin/platform?flags_status=open&flags_page=2')->assertOk()
            ->assertJsonPath('data.moderation.flags_meta.current_page', 1)->assertJsonPath('data.moderation.flags_meta.last_page', 1)
            ->assertJsonCount(25, 'data.moderation.flags')->assertJsonPath('data.summary.moderation_open', 25);
    }

    public function test_invalid_filters_are_rejected_and_specialist_data_stays_protected(): void
    {
        $this->actor('moderation.manage');
        foreach (['flags_page=0', 'reports_page=-1', 'warnings_page=no', 'users_page=0', 'clubs_page=0',
            'flags_status=invalid', 'reports_appeal=invalid', 'warnings_severity=invalid',
            'flags_category[]=invalid', 'users_q[]=invalid', 'clubs_status=invalid'] as $query) {
            $this->getJson('/api/v1/admin/platform?'.$query)->assertUnprocessable();
        }
        $this->actor('users.assign_roles');
        $this->getJson('/api/v1/admin/platform?flags_page=2&warnings_category=private')->assertOk()
            ->assertJsonPath('data.moderation.flags_meta', null)->assertJsonPath('data.moderation.reports_meta', null)
            ->assertJsonPath('data.moderation.warnings_meta', null)->assertJsonCount(0, 'data.moderation.warning_categories')
            ->assertJsonPath('data.summary.moderation_open', null)->assertJsonPath('data.summary.appeals_pending', null)
            ->assertJsonPath('data.users_meta', null)->assertJsonPath('data.clubs_meta', null);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/platform?flags_page=2')->assertForbidden();
    }

    public function test_list_queries_cannot_bypass_admin_two_factor_or_step_up(): void
    {
        $admin = $this->actor('system.manage');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/platform?users_page=2')->assertForbidden()->assertJsonPath('code', AdminTwoFactor::ERROR_CODE);
        $admin->forceFill(['two_factor_secret' => 'test-secret', 'two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/platform?users_page=2')->assertForbidden()->assertJsonPath('code', AdminTwoFactor::STEP_UP_ERROR_CODE);
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson('/api/v1/admin/platform?users_page=2')->assertOk();
    }

    private function actor(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        Sanctum::actingAs($user);

        return $user;
    }
}
