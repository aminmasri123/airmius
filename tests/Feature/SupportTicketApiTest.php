<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification as StoredNotification;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_cannot_forge_ticket_owner_or_internal_support_fields(): void
    {
        $requester = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($requester);
        $id = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'QA account question',
            'message' => 'QA isolated support request for ownership testing.',
            'category' => 'technical',
            'priority' => 'normal',
            'user_id' => $other->id,
            'email' => $other->email,
            'name' => $other->name,
            'status' => 'resolved',
            'assigned_to' => $other->id,
            'admin_note' => 'Forged internal note',
            'resolved_at' => now()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.status', 'open')->json('data.id');
        $ticket = SupportTicket::findOrFail($id);
        $this->assertSame($requester->id, $ticket->user_id);
        $this->assertSame($requester->email, $ticket->email);
        $this->assertSame($requester->name, $ticket->name);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->admin_note);
        $this->assertNull($ticket->resolved_at);
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(0, 'data');
    }

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

    public function test_ticket_cannot_be_assigned_to_a_user_without_matching_support_scope(): void
    {
        $support = User::factory()->create();
        $unprivileged = User::factory()->create();
        Permission::findOrCreate('support.tickets', 'web');
        $support->givePermissionTo('support.tickets');
        $ticket = SupportTicket::create([
            'user_id' => $unprivileged->id,
            'name' => $unprivileged->name,
            'email' => $unprivileged->email,
            'subject' => 'Geschützte Zuweisung',
            'message' => 'Diese Anfrage darf nur an berechtigten Support gehen.',
        ]);

        Sanctum::actingAs($support);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, [
            'assigned_to' => $unprivileged->id,
        ])->assertForbidden();

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_club_context_is_private_and_can_only_reference_a_linked_tenant(): void
    {
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Linked Club']);
        $foreignClub = Club::factory()->create(['owner_id' => $foreignOwner->id, 'name' => 'Foreign Club']);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Mitgliederimport prüfen',
            'message' => 'Die Vorschau zeigt eine unerwartete Zeile an.',
            'category' => 'club',
            'priority' => 'normal',
            'club_id' => $club->id,
        ])->assertCreated()
            ->assertJsonPath('data.club.id', $club->id)
            ->assertJsonPath('data.club.name', 'Linked Club')
            ->assertJsonPath('data.sla.policy_version', SupportSlaService::VERSION)
            ->assertJsonPath('data.sla.state', 'on_track');

        $this->assertArrayNotHasKey('admin_note', $response->json('data'));
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'response_sla_target_minutes' => 1440,
            'sla_target_minutes' => 4320,
        ]);

        $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Falscher Verein',
            'message' => 'Dieses Ticket darf nicht fremd zugeordnet werden.',
            'category' => 'club',
            'priority' => 'normal',
            'club_id' => $foreignClub->id,
        ])->assertForbidden();

        SupportTicket::query()->whereKey($response->json('data.id'))->update(['admin_note' => 'Intern vertraulich']);
        $list = $this->getJson('/api/v1/support/tickets')->assertOk();
        $this->assertArrayNotHasKey('admin_note', $list->json('data.0'));
    }

    public function test_cross_tenant_sla_reporting_separates_response_and_resolution_breaches(): void
    {
        $support = User::factory()->create();
        Permission::findOrCreate('support.tickets', 'web');
        $support->givePermissionTo('support.tickets');
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $clubA = Club::factory()->create(['owner_id' => $ownerA->id, 'name' => 'Alpha Club']);
        $clubB = Club::factory()->create(['owner_id' => $ownerB->id, 'name' => 'Beta Club']);
        $sla = app(SupportSlaService::class);

        SupportTicket::create([
            'user_id' => $ownerA->id,
            'club_id' => $clubA->id,
            'name' => $ownerA->name,
            'email' => $ownerA->email,
            'subject' => 'Antwort fehlt',
            'message' => 'Für dieses Ticket ist die Reaktionsfrist abgelaufen.',
            'priority' => 'urgent',
            'status' => 'open',
            ...$sla->initialAttributes('urgent'),
            'response_due_at' => now()->subMinute(),
        ]);
        SupportTicket::create([
            'user_id' => $ownerB->id,
            'club_id' => $clubB->id,
            'name' => $ownerB->name,
            'email' => $ownerB->email,
            'subject' => 'Im Zeitplan',
            'message' => 'Dieses Ticket befindet sich innerhalb aller Fristen.',
            'priority' => 'normal',
            'status' => 'open',
            ...$sla->initialAttributes('normal'),
        ]);

        Sanctum::actingAs($support);
        $this->getJson('/api/v1/admin/support/tickets')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.overdue', 1)
            ->assertJsonPath('data.summary.response_overdue', 1)
            ->assertJsonPath('data.summary.resolution_overdue', 0)
            ->assertJsonPath('data.tenants.0.club_id', $clubA->id)
            ->assertJsonPath('data.tenants.0.club_name', 'Alpha Club')
            ->assertJsonPath('data.tenants.0.overdue', 1)
            ->assertJsonPath('data.tenants.1.club_id', $clubB->id)
            ->assertJsonPath('data.abilities.cross_tenant', true);

        $this->getJson('/api/v1/admin/support/tickets?club_id='.$clubB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.club.id', $clubB->id);
    }

    public function test_club_support_manager_is_strictly_limited_to_own_tenant(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $clubA = Club::factory()->create(['owner_id' => $ownerA->id, 'name' => 'Scoped A']);
        $clubB = Club::factory()->create(['owner_id' => $ownerB->id, 'name' => 'Scoped B']);
        $ticketA = SupportTicket::create([
            'user_id' => $ownerA->id,
            'club_id' => $clubA->id,
            'name' => $ownerA->name,
            'email' => $ownerA->email,
            'subject' => 'A',
            'message' => 'Ticket aus dem ersten Mandanten.',
        ]);
        $ticketB = SupportTicket::create([
            'user_id' => $ownerB->id,
            'club_id' => $clubB->id,
            'name' => $ownerB->name,
            'email' => $ownerB->email,
            'subject' => 'B',
            'message' => 'Ticket aus dem zweiten Mandanten.',
        ]);

        Sanctum::actingAs($ownerA);
        $this->getJson('/api/v1/admin/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.id', $ticketA->id)
            ->assertJsonPath('data.abilities.cross_tenant', false)
            ->assertJsonPath('data.abilities.club_ids.0', $clubA->id);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticketB->id, ['status' => 'in_progress'])
            ->assertForbidden();
        $this->getJson('/api/v1/admin/support/tickets?club_id='.$clubB->id)->assertForbidden();
    }

    public function test_support_tenant_sla_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_09_000006_add_club_context_and_response_sla_to_support_tickets.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('support_tickets', 'club_id'));
        $this->assertFalse(Schema::hasColumn('support_tickets', 'response_due_at'));
        $this->assertFalse(Schema::hasColumn('support_tickets', 'sla_policy_version'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('support_tickets', 'club_id'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'response_due_at'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'first_response_at'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'sla_policy_version'));
    }
}
