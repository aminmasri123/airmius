<?php

namespace Tests\Feature;

use App\Models\MailDelivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAdminMailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_manager_receives_filtered_safe_mail_operations_data(): void
    {
        $admin = $this->manager();
        Sanctum::actingAs($admin);
        $recipient = User::factory()->create([
            'name' => 'Mail Kunde',
            'email' => 'mail@example.test',
        ]);
        MailDelivery::query()->create([
            'dedupe_key' => 'mobile-mail-failed',
            'mail_type' => 'invoice.created',
            'recipient_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'recipient_name' => $recipient->name,
            'status' => 'failed',
            'primary_category' => 'billing',
            'error_message' => 'SMTP unavailable',
            'context' => [
                'invoice_id' => 42,
                'token' => 'must-not-leak',
                'password' => 'must-not-leak',
            ],
        ]);
        MailDelivery::query()->create([
            'dedupe_key' => 'mobile-mail-sent',
            'mail_type' => 'inactive_account.first',
            'recipient_email' => 'other@example.test',
            'status' => 'sent',
            'primary_category' => 'system',
            'sent_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/v1/admin/mail?status=failed&type=invoice.created'
        )
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.deliveries.0.mail_type', 'invoice.created')
            ->assertJsonPath('data.deliveries.0.resendable', true)
            ->assertJsonPath('data.abilities.manage', true)
            ->assertJsonPath('data.abilities.manage_secrets', false);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('must-not-leak', $payload);
    }

    public function test_system_manager_can_update_preferences_and_resolve_delivery(): void
    {
        $admin = $this->manager();
        Sanctum::actingAs($admin);
        $delivery = MailDelivery::query()->create([
            'dedupe_key' => 'mobile-mail-resolve',
            'mail_type' => 'inactive_account.first',
            'recipient_email' => 'inactive@example.test',
            'status' => 'failed',
            'primary_category' => 'system',
            'error_message' => 'Mailbox unavailable',
        ]);

        $this->putJson('/api/v1/admin/mail/preferences', [
            'invoice_primary_category' => 'billing',
            'invoice_fallback_category' => 'support',
            'disabled_categories' => ['system', 'marketplace'],
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_primary_category', 'billing')
            ->assertJsonPath('data.disabled_categories.0', 'marketplace');

        $stored = json_decode((string) Setting::valueFor('mail_preferences'), true);
        $this->assertSame(['marketplace'], $stored['disabled_categories']);

        $this->putJson("/api/v1/admin/mail/deliveries/{$delivery->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_mail_api_requires_system_permission_and_sender_secrets_require_super_admin(): void
    {
        $this->getJson('/api/v1/admin/mail')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/mail')->assertForbidden();

        $admin = $this->manager();
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/mail/senders/system', [
            'from_address' => 'system@example.test',
            'active' => true,
        ])->assertForbidden();

        config(['airmius_mail.require_2fa_for_secret_changes' => false]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $admin->forceFill([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ])->save();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/mail/senders/system', [
            'from_address' => 'system@example.test',
            'from_name' => 'Airmius System',
            'host' => 'smtp.example.test',
            'port' => 2525,
            'username' => 'system@example.test',
            'new_password' => 'not-returned-password',
            'scheme' => 'smtp',
            'active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.address', 'system@example.test');
    }

    private function manager(): User
    {
        Permission::findOrCreate('system.manage', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('system.manage');

        return $user;
    }
}
