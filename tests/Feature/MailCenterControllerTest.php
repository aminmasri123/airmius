<?php

namespace Tests\Feature;

use App\Models\MailDelivery;
use App\Models\MailSenderAudit;
use App\Models\MailSenderSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailCenterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_manager_can_view_filtered_mail_center_deliveries(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);
        $recipient = User::factory()->create([
            'name' => 'Invoice Athlete',
            'email' => 'invoice-athlete@example.test',
        ]);

        MailDelivery::query()->create([
            'dedupe_key' => 'mail-center-filter-match',
            'mail_type' => 'invoice.created',
            'recipient_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'recipient_name' => $recipient->name,
            'status' => 'failed',
            'primary_category' => 'billing',
            'fallback_category' => 'support',
            'error_message' => 'SMTP unavailable',
        ]);

        MailDelivery::query()->create([
            'dedupe_key' => 'mail-center-filter-other',
            'mail_type' => 'inactive_account.first',
            'recipient_email' => 'other@example.test',
            'status' => 'sent',
            'primary_category' => 'system',
            'sent_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.mail-center.index', [
                'status' => 'failed',
                'type' => 'invoice.created',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/MailCenter/Index')
                ->where('filters.status', 'failed')
                ->where('filters.type', 'invoice.created')
                ->where('summary.total', 2)
                ->where('summary.failed', 1)
                ->where('categories.0', 'system')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0.mail_type', 'invoice.created')
                ->where('deliveries.data.0.status', 'failed')
                ->where('deliveries.data.0.resendable', false)
            );
    }

    public function test_system_manager_without_super_admin_role_cannot_update_sender_secrets(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $this
            ->actingAs($admin)
            ->put(route('admin.mail-center.senders.update', 'system'), [
                'from_address' => 'system@example.test',
                'from_name' => 'Airmius System',
                'host' => 'smtp.example.test',
                'port' => 2525,
                'username' => 'system@example.test',
                'scheme' => 'smtp',
                'active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('mail_sender_settings', [
            'category' => 'system',
        ]);
    }

    public function test_super_admin_can_update_sender_and_audit_without_plaintext_password(): void
    {
        config(['airmius_mail.require_2fa_for_secret_changes' => false]);

        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $this
            ->actingAs($admin)
            ->put(route('admin.mail-center.senders.update', 'system'), [
                'from_address' => 'system@example.test',
                'from_name' => 'Airmius System',
                'host' => 'smtp.example.test',
                'port' => 2525,
                'username' => 'system@example.test',
                'new_password' => 'very-secret-password',
                'scheme' => 'smtp',
                'active' => true,
            ])
            ->assertRedirect();

        $setting = MailSenderSetting::query()->where('category', 'system')->firstOrFail();
        $this->assertSame('system@example.test', $setting->from_address);
        $this->assertSame('very-secret-password', $setting->password);
        $this->assertTrue($setting->active);
        $this->assertSame($admin->id, $setting->updated_by);

        $audit = MailSenderAudit::query()->where('category', 'system')->firstOrFail();
        $this->assertSame('sender.updated', $audit->action);
        $this->assertTrue($audit->after['password_changed']);
        $this->assertArrayNotHasKey('password', $audit->after);
    }

    public function test_mail_preferences_strip_system_from_disabled_categories(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $this
            ->actingAs($admin)
            ->put(route('admin.mail-center.preferences.update'), [
                'invoice_primary_category' => 'billing',
                'invoice_fallback_category' => 'support',
                'disabled_categories' => ['system', 'marketplace'],
            ])
            ->assertRedirect();

        $stored = json_decode((string) Setting::valueFor('mail_preferences'), true);

        $this->assertSame('billing', $stored['invoice_primary_category']);
        $this->assertSame('support', $stored['invoice_fallback_category']);
        $this->assertSame(['marketplace'], $stored['disabled_categories']);
    }

    public function test_system_manager_can_resolve_failed_delivery(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $delivery = MailDelivery::query()->create([
            'dedupe_key' => 'mail-center-resolve',
            'mail_type' => 'inactive_account.first',
            'recipient_email' => 'inactive@example.test',
            'recipient_name' => 'Inactive User',
            'status' => 'failed',
            'primary_category' => 'system',
            'error_message' => 'Mailbox unavailable',
            'context' => ['attempt' => 1],
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.mail-center.resolve', $delivery))
            ->assertRedirect();

        $delivery->refresh();

        $this->assertSame('resolved', $delivery->status);
        $this->assertSame('Mailbox unavailable', $delivery->error_message);
        $this->assertSame($admin->id, $delivery->context['resolved_by']);
        $this->assertArrayHasKey('resolved_at', $delivery->context);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
