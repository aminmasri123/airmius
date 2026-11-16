<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketConfidentialAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupportTicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(User $owner, array $extra = []): SupportTicket
    {
        return SupportTicket::create(array_replace([
            'user_id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email,
            'subject' => 'Lifecycle test', 'message' => 'Private lifecycle test message',
            'category' => 'technical', 'priority' => 'normal', 'status' => 'open',
        ], $extra));
    }

    private function operator(): User
    {
        Permission::findOrCreate('support.tickets', 'web');
        $operator = User::factory()->create();
        $operator->givePermissionTo('support.tickets');

        return $operator;
    }

    public function test_withdrawal_keeps_history_blocks_replies_and_reopening_and_notifies_assignee_once(): void
    {
        $owner = User::factory()->create();
        $operator = $this->operator();
        $ticket = $this->ticket($owner, ['assigned_to' => $operator->id, 'response_due_at' => now()->subDay(), 'due_at' => now()->subHour()]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Existing message'])->assertCreated();
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/withdraw")->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.can_withdraw', false);
        $timestamp = $ticket->fresh()->withdrawn_at->toJSON();
        $this->travel(5)->minutes();
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/withdraw")->assertOk()->assertJsonPath('data.withdrawn_at', $timestamp);
        $this->getJson("/api/v1/support/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.can_reply', false)->assertJsonCount(1, 'data.messages')->assertJsonPath('data.sla.is_overdue', false);
        $this->getJson('/api/v1/support/tickets')->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Blocked reply'])->assertUnprocessable();
        Sanctum::actingAs($operator);
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Blocked staff reply'])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/support/tickets/{$ticket->id}", ['status' => 'open'])->assertUnprocessable();
        $this->getJson('/api/v1/admin/support/tickets?overdue=1')->assertOk()->assertJsonCount(0, 'data.tickets');
        $this->assertSame(1, Notification::where('type', 'support.inbox.withdrawn')->where('user_id', $operator->id)->count());
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'message' => 'Private lifecycle test message']);
    }

    public function test_hiding_is_private_reversible_and_does_not_stop_processing(): void
    {
        $owner = User::factory()->create();
        $operator = $this->operator();
        $ticket = $this->ticket($owner);
        $other = $this->ticket(User::factory()->create(), ['requester_hidden_at' => now()]);
        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/support/tickets/{$ticket->id}/visibility", ['hidden' => true])->assertOk()->assertJsonPath('data.status', 'open');
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/support/tickets?hidden=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ticket->id);
        $this->getJson("/api/v1/support/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.can_reply', true);
        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/admin/support/tickets')->assertOk()->assertJsonCount(2, 'data.tickets');
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Still processing'])->assertCreated();
        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/support/tickets/{$ticket->id}/visibility", ['hidden' => false])->assertOk()->assertJsonPath('data.requester_hidden_at', null);
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/support/tickets?hidden=1')->assertJsonCount(0, 'data');
        $this->assertNotNull($other->fresh()->requester_hidden_at);
    }

    public function test_other_users_and_support_cannot_change_requester_lifecycle(): void
    {
        $ticket = $this->ticket(User::factory()->create());
        foreach ([User::factory()->create(), $this->operator()] as $other) {
            Sanctum::actingAs($other);
            $this->postJson("/api/v1/support/tickets/{$ticket->id}/withdraw")->assertForbidden();
            $this->patchJson("/api/v1/support/tickets/{$ticket->id}/visibility", ['hidden' => true])->assertForbidden();
        }
        $this->assertNull($ticket->fresh()->withdrawn_at);
        $this->assertNull($ticket->fresh()->requester_hidden_at);
    }

    public function test_closed_tickets_can_be_hidden_but_cannot_be_withdrawn_and_input_is_validated(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        foreach (['resolved', 'closed'] as $status) {
            $ticket = $this->ticket($owner, ['status' => $status]);
            $this->postJson("/api/v1/support/tickets/{$ticket->id}/withdraw")->assertUnprocessable();
            $this->patchJson("/api/v1/support/tickets/{$ticket->id}/visibility", ['hidden' => true])->assertOk();
            $this->patchJson("/api/v1/support/tickets/{$ticket->id}/visibility", ['hidden' => 'invalid'])->assertUnprocessable();
        }
        $this->getJson('/api/v1/support/tickets?hidden=invalid')->assertUnprocessable();
    }

    public function test_confidential_withdrawal_has_an_audit_and_no_alert_to_unauthorized_assignee(): void
    {
        $owner = User::factory()->create();
        $operator = $this->operator();
        $ticket = $this->ticket($owner, ['is_confidential' => true, 'category' => 'safety', 'assigned_to' => $operator->id]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/withdraw")->assertOk();
        $this->postJson("/api/v1/support/tickets/{$ticket->id}/withdraw")->assertOk();
        $this->assertSame(1, SupportTicketConfidentialAudit::where('support_ticket_id', $ticket->id)->where('event', 'withdrawn')->count());
        $this->assertSame(0, Notification::where('type', 'support.inbox.withdrawn')->count());
    }
}
