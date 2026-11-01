<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\User;
use App\Support\AdminTwoFactor;
use App\Support\ClubAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClubWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('system.manage', 'web'));
        $user = User::factory()->create(['two_factor_secret' => 'test', 'two_factor_confirmed_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    public function test_web_and_mobile_show_only_the_selected_club(): void
    {
        $admin = $this->admin();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $other = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        ClubAuditLog::record($other, $admin, 'secret.other.club');
        $this->actingAs($admin)->get(route('admin.clubs.show', $club))->assertOk();
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson('/api/v1/admin/clubs/'.$club->id)->assertOk()
            ->assertJsonPath('data.club.id', $club->id)
            ->assertJsonPath('data.owner.id', $club->owner_id)
            ->assertJsonCount(0, 'data.history')
            ->assertJsonMissingPath('data.club.sepa_iban');
    }

    public function test_changes_are_validated_audited_and_notify_the_owner_without_changing_protected_fields(): void
    {
        $admin = $this->admin();
        $club = Club::factory()->create(['name' => 'Before', 'owner_id' => User::factory()->create()->id]);
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $url = '/api/v1/admin/clubs/'.$club->id;
        $this->patchJson($url, ['name' => '', 'contact_email' => 'broken'])->assertUnprocessable();
        $this->patchJson($url, ['name' => 'After', 'owner_id' => $admin->id])->assertOk();
        $this->assertSame('After', $club->fresh()->name);
        $this->assertSame($club->owner_id, $club->fresh()->owner_id);
        $this->assertDatabaseHas('activities', ['club_id' => $club->id, 'type' => 'club.platform.updated', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $club->owner_id, 'type' => 'club.platform.updated']);
        $count = Notification::where('type', 'club.platform.updated')->count();
        $this->patchJson($url, ['name' => 'After'])->assertOk();
        $this->assertSame($count, Notification::where('type', 'club.platform.updated')->count());
    }

    public function test_members_cannot_use_platform_workspace_even_for_their_own_club(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        Sanctum::actingAs($club->owner);
        $url = '/api/v1/admin/clubs/'.$club->id;
        $this->getJson($url)->assertForbidden();
        $this->patchJson($url, ['name' => 'Unauthorized'])->assertForbidden();
    }

    public function test_system_permission_does_not_grant_billing_or_club_role_management(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('system.manage', 'web'));
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        Sanctum::actingAs($user, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson('/api/v1/admin/clubs/'.$club->id)->assertOk()
            ->assertJsonPath('data.canManageSubscription', false)
            ->assertJsonPath('data.canManageRoles', false)
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.invoices', null)
            ->assertJsonCount(0, 'data.plans');
        $this->putJson('/api/v1/admin/backoffice/clubs/'.$club->id.'/subscription', [
            'subscription_plan_id' => $club->currentSubscription?->subscription_plan_id, 'status' => 'cancelled',
        ])->assertForbidden();
    }
}
