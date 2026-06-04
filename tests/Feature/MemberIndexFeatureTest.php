<?php

namespace Tests\Feature;

use App\Models\AccountWarning;
use App\Models\MailDelivery;
use App\Models\ModerationFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MemberIndexFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_index_forces_inactivity_tab_back_to_users_without_system_permission(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['users.view']);
        $warnedUser = User::factory()->create([
            'name' => 'Warned Athlete',
            'email' => 'warned@example.test',
        ]);
        $flag = ModerationFlag::query()->create([
            'user_id' => $warnedUser->id,
            'source' => 'test',
            'severity' => 'medium',
            'categories' => ['chat'],
            'matched_terms' => ['blocked-word'],
            'status' => 'open',
        ]);

        AccountWarning::query()->create([
            'user_id' => $warnedUser->id,
            'moderation_flag_id' => $flag->id,
            'severity' => 'medium',
            'points' => 2,
            'reason' => 'Automatische Moderationswarnung',
        ]);

        $this->actingAs($admin)
            ->get(route('members.index', ['tab' => 'inactivity']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Users/Index')
                ->where('canManageInactivity', false)
                ->where('filters.tab', 'users')
                ->has('warnings', 1)
                ->where('warnings.0.flag.categories.0', 'chat')
                ->where('inactiveUsers.total', 0)
            );
    }

    public function test_system_manager_can_open_inactivity_tab_and_filter_inactive_members(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['users.view', 'system.manage']);
        $inactiveUser = User::factory()->create([
            'name' => 'Dormant Athlete',
            'email' => 'dormant@example.test',
            'last_login_at' => now()->subMonthsNoOverflow(13),
            'last_seen_at' => null,
            'created_at' => now()->subMonthsNoOverflow(14),
            'updated_at' => now()->subMonthsNoOverflow(13),
        ]);

        User::factory()->create([
            'name' => 'Fresh Athlete',
            'email' => 'fresh@example.test',
            'last_login_at' => now(),
        ]);

        MailDelivery::query()->create([
            'dedupe_key' => 'inactive-test-'.$inactiveUser->id,
            'mail_type' => 'inactive_account.first',
            'recipient_id' => $inactiveUser->id,
            'recipient_email' => $inactiveUser->email,
            'recipient_name' => $inactiveUser->name,
            'status' => 'failed',
            'primary_category' => 'support',
            'fallback_category' => 'billing',
            'error_message' => 'SMTP unavailable',
        ]);

        $this->actingAs($admin)
            ->get(route('members.index', [
                'tab' => 'inactivity',
                'inactive_stage' => '12',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageInactivity', true)
                ->where('filters.tab', 'inactivity')
                ->where('filters.inactive_stage', '12')
                ->where('inactiveSummary.inactive_12', 1)
                ->where('inactiveSummary.mail_failed', 1)
                ->has('inactiveUsers.data', 1)
                ->where('inactiveUsers.data.0.id', $inactiveUser->id)
                ->where('inactiveUsers.data.0.recommended_stage', 'first')
                ->where('inactiveUsers.data.0.last_mail.status', 'failed')
            );
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
