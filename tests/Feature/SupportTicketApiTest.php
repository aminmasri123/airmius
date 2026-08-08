<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Notification as StoredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_own_support_tickets(): void
    {
        $user = User::factory()->create(['name' => 'Support User']);
        Sanctum::actingAs($user);

        $ticketId = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Login funktioniert nicht',
            'message' => 'Nach der Anmeldung bleibt die Seite leer.',
            'category' => 'technical',
            'priority' => 'high',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->json('data.id');

        $this->getJson('/api/v1/support/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticketId)
            ->assertJsonPath('data.0.subject', 'Login funktioniert nicht');

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_support_tickets_are_private_and_validation_is_safe(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        SupportTicket::create([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'subject' => 'Privat',
            'message' => 'Private Support-Nachricht.',
        ]);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/support/tickets', [
            'subject' => 'x',
            'message' => 'zu kurz',
            'category' => 'unknown',
            'priority' => 'normal',
        ])->assertUnprocessable();
    }

    public function test_support_staff_can_filter_update_and_escalate_tickets_with_sla_data(): void
    {
        $requester = User::factory()->create(['name' => 'Requester', 'language' => 'ar']);
        $support = User::factory()->create(['name' => 'Support Agent']);
        Permission::findOrCreate('support.tickets', 'web');
        $support->givePermissionTo('support.tickets');

        Sanctum::actingAs($requester);
        $ticketId = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Dringender Clubfehler',
            'message' => 'Der geschützte Vereinsbereich bleibt nach dem Laden leer.',
            'category' => 'club',
            'priority' => 'urgent',
        ])->assertCreated()
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.is_overdue', false)
            ->json('data.id');

        Sanctum::actingAs($support);
        $this->getJson('/api/v1/admin/support/tickets?priority=urgent')
            ->assertOk()
            ->assertJsonPath('data.tickets.0.id', $ticketId)
            ->assertJsonPath('data.tickets.0.requester.name', 'Requester')
            ->assertJsonPath('data.summary.urgent', 1);

        $this->patchJson('/api/v1/admin/support/tickets/'.$ticketId, [
            'status' => 'in_progress',
            'assigned_to' => $support->id,
            'escalated' => true,
            'admin_note' => 'An Entwicklung weitergegeben.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assignee.id', $support->id)
            ->assertJsonPath('data.admin_note', 'An Entwicklung weitergegeben.');

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'assigned_to' => $support->id,
            'status' => 'in_progress',
        ]);
        $this->assertNotNull(SupportTicket::find($ticketId)?->escalated_at);
        $notification = StoredNotification::query()
            ->where('user_id', $requester->id)
            ->where('type', 'support.ticket_updated')
            ->firstOrFail();
        $this->assertSame('ar', data_get($notification->data, 'locale'));
        $this->assertSame(
            trans('support.notifications.updated_title', locale: 'ar'),
            data_get($notification->data, 'title'),
        );
        $this->assertSame('support.statuses.in_progress', data_get($notification->data, 'i18n.replace.status.translation_key'));
    }

    public function test_support_admin_endpoints_are_denied_without_support_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/support/tickets')->assertForbidden();
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Privat',
            'message' => 'Private Support-Nachricht.',
        ]);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['status' => 'resolved'])
            ->assertForbidden();
    }
}
