<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportInboxNotificationService;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupportInboxNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function operator(bool $safety = false): User
    {
        Permission::findOrCreate('support.tickets', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('support.tickets');
        if ($safety) {
            $user->givePermissionTo('support.safety');
        }

        return $user;
    }

    private function admin(bool $enabled = true): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['notification_channels' => ['support_admin_alerts' => $enabled]]);
        $user->assignRole('admin');
        $user->forceFill(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function createTicket(User $requester, array $extra = []): SupportTicket
    {
        Sanctum::actingAs($requester);
        $id = $this->postJson('/api/v1/support/tickets', array_replace([
            'recipient' => 'airmius', 'subject' => 'Private subject never in alerts',
            'message' => 'Private description never in alerts', 'category' => 'technical', 'priority' => 'normal',
        ], $extra))->assertCreated()->json('data.id');

        return SupportTicket::findOrFail($id);
    }

    public function test_super_admin_receives_and_opens_deletion_review_without_safety_access(): void
    {
        $admin = $this->admin();
        Role::findOrCreate('super_admin', 'web');
        $admin->syncRoles(['super_admin']);
        $requester = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $requester->id, 'name' => $requester->name, 'email' => $requester->email,
            'subject' => 'Deletion review', 'message' => 'Review financial retention',
            'category' => 'privacy', 'priority' => 'high', 'status' => 'open',
            'is_confidential' => true, 'report_source' => 'club_deletion_review',
        ]);
        $notice = Notification::where('user_id', $admin->id)->where('type', 'support.inbox.new')->firstOrFail();
        $this->assertStringContainsString('inbox=general', $notice->data['url']);
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::tokenAbility()]);
        $this->getJson('/api/v1/admin/support/tickets?inbox=general')->assertOk()
            ->assertJsonPath('data.tickets.0.id', $ticket->id);
        $this->getJson('/api/v1/support/tickets/'.$ticket->id)->assertOk();
        $this->getJson('/api/v1/admin/support/tickets?inbox=safety')->assertForbidden();
    }

    public function test_new_request_notifies_support_and_opted_in_admins_once_without_private_text(): void
    {
        $support = $this->operator();
        $admin = $this->admin();
        $disabled = $this->admin(false);
        $requester = User::factory()->create();
        $ticket = $this->createTicket($requester);
        $this->assertEqualsCanonicalizing([$support->id, $admin->id], Notification::where('type', 'support.inbox.new')->pluck('user_id')->all());
        $this->assertStringNotContainsString($ticket->subject, Notification::first()->toJson());
        $this->assertStringNotContainsString($ticket->message, Notification::first()->toJson());
        app(SupportInboxNotificationService::class)->created($ticket);
        $this->assertSame(2, Notification::where('type', 'support.inbox.new')->count());
        $this->assertDatabaseMissing('notifications', ['user_id' => $disabled->id]);
    }

    public function test_admin_can_disable_only_new_inbox_alerts_and_assignment_still_notifies(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/support/inbox-preferences', ['support_admin_alerts' => false])->assertOk();
        $this->assertFalse($admin->fresh()->notification_channels['support_admin_alerts']);
        $ticket = $this->createTicket(User::factory()->create());
        $this->assertSame(0, Notification::where('user_id', $admin->id)->count());
        $operator = $this->operator();
        Sanctum::actingAs($operator);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['assigned_to' => $admin->id])->assertOk();
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'support.inbox.assigned']);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['assigned_to' => $admin->id])->assertOk();
        $this->assertSame(1, Notification::where('type', 'support.inbox.assigned')->count());
        $this->patchJson('/api/v1/support/inbox-preferences', ['support_admin_alerts' => false])->assertForbidden();
    }

    public function test_safety_inbox_and_notifications_require_explicit_permission_even_for_admins(): void
    {
        $normal = $this->operator();
        $admin = $this->admin();
        $safety = $this->operator(true);
        $requester = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $requester->id]);
        $ticket = $this->createTicket($requester, ['recipient' => 'club', 'club_id' => $club->id, 'category' => 'safety']);
        $this->assertEquals([$safety->id], Notification::where('type', 'support.inbox.new')->pluck('user_id')->all());
        foreach ([$normal, $admin] as $unauthorized) {
            Sanctum::actingAs($unauthorized, ['*', AdminTwoFactor::tokenAbility()]);
            $this->getJson('/api/v1/admin/support/tickets')->assertOk()->assertJsonCount(0, 'data.tickets')->assertJsonPath('data.summary.total', 0);
            $this->getJson('/api/v1/admin/support/tickets?inbox=safety')->assertForbidden();
            $this->getJson('/api/v1/support/tickets/'.$ticket->id)->assertForbidden();
            $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['assigned_to' => $unauthorized->id])->assertForbidden();
        }
        Sanctum::actingAs($safety);
        $this->getJson('/api/v1/admin/support/tickets?inbox=safety')->assertOk()->assertJsonPath('data.tickets.0.id', $ticket->id);
        $this->getJson('/api/v1/support/tickets/'.$ticket->id)->assertOk()
            ->assertJsonPath('data.assignees', [['id' => $safety->id, 'name' => $safety->name]]);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['assigned_to' => $normal->id])->assertForbidden();
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['assigned_to' => $safety->id])->assertOk();
        $ticket->update(['conflict_user_ids' => [$safety->id]]);
        $this->getJson('/api/v1/admin/support/tickets?inbox=safety')->assertOk()->assertJsonCount(0, 'data.tickets');
        $this->getJson('/api/v1/support/tickets/'.$ticket->id)->assertForbidden();
        Sanctum::actingAs($requester);
        $this->getJson('/api/v1/support/tickets/'.$ticket->id)->assertOk();
    }

    public function test_club_inbox_notifications_follow_club_scope_and_do_not_notify_regular_members(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $requester = User::factory()->create();
        $regular = User::factory()->create();
        $clubA = Club::factory()->create(['owner_id' => $ownerA->id]);
        Club::factory()->create(['owner_id' => $ownerB->id]);
        foreach ([$requester, $regular] as $member) {
            $clubA->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        }
        $this->createTicket($requester, ['recipient' => 'club', 'club_id' => $clubA->id]);
        $this->assertEquals([$ownerA->id], Notification::where('type', 'support.inbox.new')->pluck('user_id')->all());
    }

    public function test_revoked_safety_access_prevents_followup_notifications(): void
    {
        $safety = $this->operator(true);
        $requester = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $requester->id]);
        $ticket = $this->createTicket($requester, ['recipient' => 'club', 'club_id' => $club->id, 'category' => 'safety']);
        $ticket->update(['assigned_to' => $safety->id]);
        $safety->revokePermissionTo('support.safety');
        Sanctum::actingAs($requester);
        $this->postJson('/api/v1/support/tickets/'.$ticket->id.'/messages', ['body' => 'Private follow-up text'])->assertCreated();
        $this->assertSame(0, Notification::where('type', 'support.requester_reply')->count());
    }

    public function test_safety_setup_command_preserves_existing_roles_and_requires_an_existing_account(): void
    {
        $user = $this->operator();
        Role::findOrCreate('support', 'web');
        $user->assignRole('support');
        $this->artisan('airmius:configure-support', ['--safety-user' => $user->email, '--dry-run' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->hasRole('safety_support'));
        $this->artisan('airmius:configure-support', ['--safety-user' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasAllRoles(['support', 'safety_support']));
        $this->artisan('airmius:configure-support', ['--safety-user' => 'missing@example.test'])->assertFailed();
    }

    public function test_maintenance_keeps_social_login_available_for_admin_sign_in(): void
    {
        Setting::setValue('maintenance_mode', true);
        Route::middleware('web')->get('/test-social-login', fn () => response('provider redirect'))
            ->name('social-auth.test');
        $this->get('/test-social-login')->assertOk()->assertSee('provider redirect');
        $this->get('/')->assertStatus(503);
    }

    public function test_only_step_up_authenticated_super_admin_can_explicitly_enroll_a_safety_operator(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::tokenAbility()]);
        $this->postJson('/api/v1/admin/support/safety-operators', ['email' => $admin->email])->assertForbidden();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin, ['api']);
        $this->postJson('/api/v1/admin/support/safety-operators', ['email' => $admin->email])->assertForbidden();
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::tokenAbility()]);
        $this->postJson('/api/v1/admin/support/safety-operators', ['email' => $admin->email])->assertOk();
        $this->assertTrue($admin->fresh()->hasAllRoles(['admin', 'super_admin', 'safety_support']));
        $this->postJson('/api/v1/admin/support/safety-operators', ['email' => 'missing@example.test'])->assertUnprocessable();
    }
}
