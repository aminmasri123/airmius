<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventParticipationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_participation_cancellation_is_idempotent_and_refunds_once(): void
    {
        [$event, $participant, $payment] = $this->paidParticipant();

        Sanctum::actingAs($participant->user);

        $payload = [
            'reason' => 'Illness',
            'refund' => true,
            'idempotency_key' => 'cancel-event-seat-1',
        ];

        $this->postJson("/api/v1/events/{$event->id}/participation/cancel", $payload)
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'no');

        $this->postJson("/api/v1/events/{$event->id}/participation/cancel", $payload)
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'no');

        $participant->refresh();

        $this->assertSame('refunded', $participant->lifecycle_status);
        $this->assertNotNull($participant->refund_payment_id);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', [
            'id' => $participant->refund_payment_id,
            'club_id' => $payment->club_id,
            'user_id' => $participant->user_id,
            'amount' => '-15',
            'status' => 'refunded',
            'purpose' => 'event_refund',
        ]);
    }

    public function test_substitute_assigns_payment_to_replacement_once(): void
    {
        [$event, $participant, $payment] = $this->paidParticipant();
        $replacement = User::factory()->create();

        Sanctum::actingAs($participant->user);

        $payload = [
            'replacement_user_id' => $replacement->id,
            'reason' => 'Seat transferred',
            'idempotency_key' => 'substitute-event-seat-1',
        ];

        $this->postJson("/api/v1/events/{$event->id}/participation/substitute", $payload)
            ->assertOk();
        $this->postJson("/api/v1/events/{$event->id}/participation/substitute", $payload)
            ->assertOk();

        $participant->refresh();
        $replacementParticipant = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('user_id', $replacement->id)
            ->sole();

        $this->assertSame('replacement_assigned', $participant->lifecycle_status);
        $this->assertSame('confirmed', $replacementParticipant->lifecycle_status);
        $this->assertSame($payment->id, $replacementParticipant->payment_id);
        $this->assertSame($participant->id, $replacementParticipant->replacement_for_participant_id);
        $this->assertSame(1, EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('replacement_for_participant_id', $participant->id)
            ->count());
    }

    public function test_cancellation_below_minimum_cancels_event_state(): void
    {
        [$event, $participant] = $this->paidParticipant(['min_participants' => 2]);
        $second = User::factory()->create();

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $second->id,
            'status' => 'yes',
            'lifecycle_status' => 'confirmed',
        ]);

        Sanctum::actingAs($participant->user);

        $this->postJson("/api/v1/events/{$event->id}/participation/cancel", [
            'reason' => 'Cannot attend',
            'refund' => false,
            'idempotency_key' => 'minimum-cancel-1',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $event->fresh()->status);
        $this->assertSame('minimum_participants_not_reached', $event->fresh()->cancellation_reason);
    }

    private function paidParticipant(array $eventOverrides = []): array
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($user->id, ['role' => 'member', 'membership_status' => 'active']);

        $event = Event::query()->create(array_merge([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Paid Clinic',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'member_price_cents' => 1500,
            'min_participants' => null,
        ], $eventOverrides));

        $payment = Payment::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'purpose' => 'event_registration',
            'amount' => 15,
            'status' => 'paid',
            'method' => 'bank_transfer',
            'reference' => 'EVT-'.$event->id,
            'paid_at' => now(),
        ]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'status' => 'yes',
            'rsvp_status' => 'yes',
            'lifecycle_status' => 'confirmed',
        ]);
        $participant->setRelation('user', $user);

        return [$event, $participant, $payment];
    }
}
