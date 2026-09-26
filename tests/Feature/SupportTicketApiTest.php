<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Notification as StoredNotification;
use App\Models\SupportTicket;
use App\Models\SupportTicketConfidentialAudit;
use App\Models\Team;
use App\Models\User;
use App\Services\SupportSlaService;
use App\Support\ClubPermissions;
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

    public function test_public_confidential_safety_report_can_be_anonymous_and_does_not_echo_sensitive_content(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Safe Club']);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend']);
        $team = Team::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'name' => 'U16',
            'sport_type' => 'football',
        ]);

        $response = $this->postJson('/api/v1/safety/reports', [
            'club_id' => $club->id,
            'team_id' => $team->id,
            'subject' => 'Vertrauliche Schutzmeldung',
            'message' => 'Ich moechte einen vertraulichen Vorfall im Jugendtraining melden.',
            'report_type' => 'safeguarding',
            'priority' => 'urgent',
            'anonymous' => true,
            'allow_follow_up' => true,
            'contact_name' => 'Soll nicht gespeichert werden',
            'contact_email' => 'ignored@example.test',
            'affected_person_reference' => 'Jugendgruppe Dienstag',
        ])->assertCreated()
            ->assertJsonPath('data.category', 'safety')
            ->assertJsonPath('data.is_confidential', true)
            ->assertJsonPath('data.is_anonymous', true)
            ->assertJsonPath('data.allow_follow_up', false)
            ->assertJsonPath('data.safety_report_type', 'safeguarding')
            ->assertJsonPath('data.club.id', $club->id)
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.team.id', $team->id);

        $this->assertArrayNotHasKey('message', $response->json('data'));
        $this->assertArrayNotHasKey('requester', $response->json('data'));
        $this->assertArrayNotHasKey('affected_person_reference', $response->json('data'));

        $this->assertDatabaseHas('support_tickets', [
            'id' => $response->json('data.id'),
            'user_id' => null,
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'team_id' => $team->id,
            'name' => null,
            'email' => null,
            'category' => 'safety',
            'priority' => 'urgent',
            'is_confidential' => true,
            'is_anonymous' => true,
            'allow_follow_up' => false,
            'safety_report_type' => 'safeguarding',
            'affected_person_reference' => 'Jugendgruppe Dienstag',
            'report_source' => 'public_safety_channel',
        ]);
    }

    public function test_confidential_safety_report_requires_contact_when_not_anonymous_and_stays_internal(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Visible Club']);

        $this->postJson('/api/v1/safety/reports', [
            'club_id' => $club->id,
            'subject' => 'Nicht anonyme Meldung',
            'message' => 'Ich bin fuer Rueckfragen erreichbar und melde einen Unfall.',
            'report_type' => 'accident',
            'anonymous' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_name', 'contact_email']);

        $ticketId = $this->postJson('/api/v1/safety/reports', [
            'club_id' => $club->id,
            'subject' => 'Nicht anonyme Meldung',
            'message' => 'Ich bin fuer Rueckfragen erreichbar und melde einen Unfall.',
            'report_type' => 'accident',
            'anonymous' => false,
            'allow_follow_up' => true,
            'contact_name' => 'Vertrauensperson',
            'contact_email' => 'trust@example.test',
        ])->assertCreated()
            ->assertJsonPath('data.is_anonymous', false)
            ->assertJsonPath('data.allow_follow_up', true)
            ->json('data.id');

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/support/tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $support = $this->supportActor($club, $owner, 'safety_support_viewer', [ClubPermissions::SUPPORT_VIEW]);
        Sanctum::actingAs($support);
        $this->getJson('/api/v1/admin/support/tickets?category=safety')
            ->assertOk()
            ->assertJsonPath('data.tickets.0.id', $ticketId)
            ->assertJsonPath('data.tickets.0.requester.name', 'Vertrauensperson')
            ->assertJsonPath('data.tickets.0.requester.email', 'trust@example.test')
            ->assertJsonPath('data.tickets.0.is_confidential', true)
            ->assertJsonPath('data.tickets.0.safety_report_type', 'accident');
    }

    public function test_public_confidential_safety_report_rejects_foreign_scope(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignTeam = Team::query()->create([
            'club_id' => $foreignClub->id,
            'name' => 'Fremdteam',
            'sport_type' => 'football',
        ]);

        $this->postJson('/api/v1/safety/reports', [
            'club_id' => $club->id,
            'team_id' => $foreignTeam->id,
            'subject' => 'Falscher Bereich',
            'message' => 'Diese Meldung darf keinem fremden Team zugeordnet werden.',
            'report_type' => 'conduct',
            'anonymous' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');
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

    public function test_club_support_actions_are_separated_and_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => $foreignOwner->id]);
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'subject' => 'Getrennte Supportrechte',
            'message' => 'Jede Aktion benötigt eine eigene Berechtigung.',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $foreignTicket = SupportTicket::create([
            'user_id' => $foreignOwner->id,
            'club_id' => $foreignClub->id,
            'name' => $foreignOwner->name,
            'email' => $foreignOwner->email,
            'subject' => 'Fremdes Ticket',
            'message' => 'Dieses Ticket muss außerhalb des sichtbaren Mandanten bleiben.',
        ]);

        $viewer = $this->supportActor($club, $owner, 'support_viewer', [ClubPermissions::SUPPORT_VIEW]);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/admin/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.id', $ticket->id)
            ->assertJsonPath('data.tickets.0.can_edit', false)
            ->assertJsonPath('data.tickets.0.can_assign', false)
            ->assertJsonPath('data.tickets.0.can_resolve', false);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['priority' => 'high'])->assertForbidden();

        $editor = $this->supportActor($club, $owner, 'support_editor', [ClubPermissions::SUPPORT_EDIT]);
        Sanctum::actingAs($editor);
        $this->getJson('/api/v1/admin/support/tickets')
            ->assertOk()
            ->assertJsonPath('data.tickets.0.can_edit', true)
            ->assertJsonPath('data.tickets.0.can_assign', false)
            ->assertJsonPath('data.tickets.0.can_resolve', false);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, [
            'status' => 'in_progress',
            'priority' => 'high',
            'admin_note' => 'Bearbeitung begonnen.',
        ])->assertOk();
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['status' => 'resolved'])->assertForbidden();

        $assigner = $this->supportActor($club, $owner, 'support_assigner', [ClubPermissions::SUPPORT_ASSIGN]);
        Sanctum::actingAs($assigner);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['assigned_to' => $editor->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $editor->id);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['priority' => 'urgent'])->assertForbidden();

        $resolver = $this->supportActor($club, $owner, 'support_resolver', [ClubPermissions::SUPPORT_RESOLVE]);
        Sanctum::actingAs($resolver);
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
        $this->patchJson('/api/v1/admin/support/tickets/'.$ticket->id, ['status' => 'open'])->assertForbidden();
        $this->patchJson('/api/v1/admin/support/tickets/'.$foreignTicket->id, ['status' => 'closed'])->assertForbidden();
    }

    public function test_department_and_team_support_roles_only_see_and_change_their_own_scope(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend']);
        $otherDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Erwachsene']);
        $team = Team::query()->create(['club_id' => $club->id, 'club_department_id' => $department->id, 'name' => 'U18', 'sport_type' => 'football']);
        $otherTeam = Team::query()->create(['club_id' => $club->id, 'club_department_id' => $otherDepartment->id, 'name' => 'Herren', 'sport_type' => 'football']);

        $clubTicket = $this->ticketInScope($owner, $club);
        $departmentTicket = $this->ticketInScope($owner, $club, $department);
        $teamTicket = $this->ticketInScope($owner, $club, $department, $team);
        $otherTeamTicket = $this->ticketInScope($owner, $club, $otherDepartment, $otherTeam);

        $departmentViewer = $this->supportActor(
            $club,
            $owner,
            'department_support_viewer',
            [ClubPermissions::SUPPORT_VIEW],
            'department',
            $department->id,
        );
        Sanctum::actingAs($departmentViewer);
        $response = $this->getJson('/api/v1/admin/support/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data.tickets')
            ->assertJsonFragment(['id' => $departmentTicket->id, 'name' => 'Jugend'])
            ->assertJsonFragment(['id' => $teamTicket->id, 'name' => 'U18']);
        $this->assertEqualsCanonicalizing(
            [$departmentTicket->id, $teamTicket->id],
            collect($response->json('data.tickets'))->pluck('id')->all(),
        );
        $this->getJson('/api/v1/admin/support/tickets?club_id='.$club->id)
            ->assertOk()->assertJsonCount(2, 'data.tickets');
        $this->getJson('/api/v1/admin/support/tickets?club_department_id='.$otherDepartment->id)
            ->assertForbidden();

        $teamEditor = $this->supportActor(
            $club,
            $owner,
            'team_support_editor',
            [ClubPermissions::SUPPORT_EDIT],
            'team',
            $team->id,
        );
        Sanctum::actingAs($teamEditor);
        $this->getJson('/api/v1/admin/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.id', $teamTicket->id)
            ->assertJsonPath('data.tickets.0.can_edit', true);
        $this->patchJson('/api/v1/admin/support/tickets/'.$teamTicket->id, ['priority' => 'high'])->assertOk();
        $this->patchJson('/api/v1/admin/support/tickets/'.$departmentTicket->id, ['priority' => 'high'])->assertForbidden();
    }

    public function test_requester_support_scope_must_belong_to_the_linked_club(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend']);
        $foreignTeam = Team::query()->create(['club_id' => $foreignClub->id, 'name' => 'Fremd', 'sport_type' => 'football']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Mannschaftsfrage',
            'message' => 'Diese Anfrage gehört in den ausgewählten Bereich.',
            'category' => 'club',
            'priority' => 'normal',
            'club_id' => $club->id,
            'club_department_id' => $department->id,
        ])->assertCreated()->assertJsonPath('data.department.id', $department->id);

        $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Falsche Mannschaft',
            'message' => 'Eine fremde Mannschaft darf nicht als Bereich verwendet werden.',
            'category' => 'club',
            'priority' => 'normal',
            'club_id' => $club->id,
            'team_id' => $foreignTeam->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('team_id');
    }

    public function test_support_tenant_sla_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_09_000006_add_club_context_and_response_sla_to_support_tickets.php');
        $scopeMigration = require database_path('migrations/2026_09_25_000008_scope_support_tickets.php');

        $scopeMigration->down();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('support_tickets', 'club_id'));
        $this->assertFalse(Schema::hasColumn('support_tickets', 'response_due_at'));
        $this->assertFalse(Schema::hasColumn('support_tickets', 'sla_policy_version'));

        $migration->up();
        $scopeMigration->up();
        $this->assertTrue(Schema::hasColumn('support_tickets', 'club_id'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'response_due_at'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'first_response_at'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'sla_policy_version'));
    }

    public function test_support_scope_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_25_000008_scope_support_tickets.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('support_tickets', 'club_department_id'));
        $this->assertFalse(Schema::hasColumn('support_tickets', 'team_id'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('support_tickets', 'club_department_id'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'team_id'));
    }

    public function test_confidential_safety_report_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_26_000020_add_confidential_safety_fields_to_support_tickets.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('support_tickets', 'is_confidential'));
        $this->assertFalse(Schema::hasColumn('support_tickets', 'safety_report_type'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('support_tickets', 'is_confidential'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'is_anonymous'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'allow_follow_up'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'safety_report_type'));
        $this->assertTrue(Schema::hasColumn('support_tickets', 'affected_person_reference'));
    }

    public function test_confidential_case_file_tracks_responsibility_conflicts_actions_and_hash_audit(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($requester->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $caseLead = $this->supportActor($club, $owner, 'case_lead', [ClubPermissions::SUPPORT_EDIT, ClubPermissions::SUPPORT_ASSIGN]);
        $conflicted = $this->supportActor($club, $owner, 'conflicted_case_worker', [ClubPermissions::SUPPORT_EDIT]);

        Sanctum::actingAs($requester);
        $ticketId = $this->postJson('/api/v1/safety/reports', [
            'club_id' => $club->id,
            'subject' => 'Vertraulicher Vorfall',
            'message' => 'Bitte vertraulich bearbeiten, es geht um eine sensible Schutzmeldung.',
            'report_type' => 'safeguarding',
            'case_group' => 'safeguarding_minor',
            'anonymous' => false,
            'contact_name' => 'Concerned Member',
            'contact_email' => 'concerned@example.test',
            'affected_person_reference' => 'Jugendteam U15',
        ])->assertCreated()
            ->assertJsonPath('data.confidential_case_group', 'safeguarding_minor')
            ->json('data.id');

        $createdAudit = SupportTicketConfidentialAudit::query()
            ->where('support_ticket_id', $ticketId)
            ->firstOrFail();
        $this->assertSame('created', $createdAudit->event);
        $this->assertNull($createdAudit->previous_hash);
        $this->assertNotEmpty($createdAudit->event_hash);

        Sanctum::actingAs($caseLead);
        $this->patchJson("/api/v1/admin/support/tickets/{$ticketId}", [
            'status' => 'in_progress',
            'responsible_user_id' => $caseLead->id,
            'conflict_user_ids' => [$conflicted->id],
            'protective_action_summary' => 'Fallgruppe begrenzt, Kontakt nur ueber Kinderschutzbeauftragte.',
        ])->assertOk()
            ->assertJsonPath('data.responsible.id', $caseLead->id)
            ->assertJsonPath('data.conflict_user_ids.0', $conflicted->id)
            ->assertJsonPath('data.protective_action_summary', 'Fallgruppe begrenzt, Kontakt nur ueber Kinderschutzbeauftragte.');

        $this->patchJson("/api/v1/admin/support/tickets/{$ticketId}", [
            'assigned_to' => $conflicted->id,
            'conflict_user_ids' => [$conflicted->id],
        ])->assertUnprocessable();

        $audits = SupportTicketConfidentialAudit::query()
            ->where('support_ticket_id', $ticketId)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $audits);
        $this->assertSame('updated', $audits[1]->event);
        $this->assertSame($audits[0]->event_hash, $audits[1]->previous_hash);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'responsible_user_id' => $caseLead->id,
            'protective_action_summary' => 'Fallgruppe begrenzt, Kontakt nur ueber Kinderschutzbeauftragte.',
        ]);
    }

    private function supportActor(
        Club $club,
        User $owner,
        string $key,
        array $permissions,
        string $scopeType = 'club',
        ?int $scopeId = null,
    ): User {
        $actor = User::factory()->create();
        $club->users()->attach($actor->id, [
            'role' => 'financial_controller',
            'roles' => ['financial_controller'],
            'membership_status' => 'active',
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $actor->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'scope_key' => $scopeType === 'club' ? 'club' : $scopeType.':'.$scopeId,
            'assigned_by' => $owner->id,
        ]);

        return $actor;
    }

    private function ticketInScope(
        User $requester,
        Club $club,
        ?ClubDepartment $department = null,
        ?Team $team = null,
    ): SupportTicket {
        return SupportTicket::query()->create([
            'user_id' => $requester->id,
            'club_id' => $club->id,
            'club_department_id' => $department?->id,
            'team_id' => $team?->id,
            'name' => $requester->name,
            'email' => $requester->email,
            'subject' => 'Support '.$club->id.' '.($department?->id ?? 'club').' '.($team?->id ?? ''),
            'message' => 'Bereichsgebundene Supportanfrage.',
            'status' => 'open',
            'priority' => 'normal',
        ]);
    }
}
