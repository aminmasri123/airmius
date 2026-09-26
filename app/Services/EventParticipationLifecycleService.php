<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventParticipationLifecycleService
{
    public function cancel(Event $event, User $user, array $data = []): EventParticipant
    {
        return DB::transaction(function () use ($event, $user, $data): EventParticipant {
            $lockedEvent = $this->lockedEvent($event);
            $participant = $this->lockedParticipant($lockedEvent, $user);
            $key = $data['idempotency_key'] ?? null;

            if ($this->alreadyApplied($participant, $key, ['cancelled', 'refunded', 'replacement_assigned', 'event_cancelled'])) {
                return $participant;
            }

            if (in_array($participant->lifecycle_status, ['cancelled', 'refunded', 'replacement_assigned', 'event_cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => __('server.events.lifecycle_already_closed'),
                ]);
            }

            $participant->forceFill([
                'status' => 'no',
                'rsvp_status' => 'no',
                'lifecycle_status' => 'cancelled',
                'cancelled_at' => now(),
                'lifecycle_idempotency_key' => $key,
                'lifecycle_note' => $data['reason'] ?? $participant->lifecycle_note,
            ])->save();

            if ($this->shouldRefund($participant, $data)) {
                $this->refundParticipant($lockedEvent, $participant, $data);
            }

            $this->cancelEventIfBelowMinimum($lockedEvent, $data);

            return $participant->fresh();
        });
    }

    public function substitute(Event $event, User $originalUser, User $replacementUser, array $data = []): EventParticipant
    {
        return DB::transaction(function () use ($event, $originalUser, $replacementUser, $data): EventParticipant {
            $lockedEvent = $this->lockedEvent($event);
            $original = $this->lockedParticipant($lockedEvent, $originalUser);
            $key = $data['idempotency_key'] ?? null;

            $existingReplacement = EventParticipant::query()
                ->where('event_id', $lockedEvent->id)
                ->where('replacement_for_participant_id', $original->id)
                ->when($key, fn ($query) => $query->where('lifecycle_idempotency_key', $key))
                ->lockForUpdate()
                ->first();

            if ($existingReplacement) {
                return $existingReplacement;
            }

            if (in_array($original->lifecycle_status, ['refunded', 'event_cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => __('server.events.lifecycle_already_closed'),
                ]);
            }

            $replacement = EventParticipant::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $replacementUser->id)
                ->lockForUpdate()
                ->first();

            if ($replacement && $replacement->status === 'yes') {
                throw ValidationException::withMessages([
                    'replacement_user_id' => __('server.events.replacement_already_registered'),
                ]);
            }

            $replacement = EventParticipant::query()->updateOrCreate(
                ['event_id' => $lockedEvent->id, 'user_id' => $replacementUser->id],
                [
                    'status' => 'yes',
                    'rsvp_status' => 'yes',
                    'lifecycle_status' => 'confirmed',
                    'payment_id' => $original->payment_id,
                    'replacement_for_participant_id' => $original->id,
                    'response_mode' => $data['response_mode'] ?? 'system',
                    'responded_at' => now(),
                    'lifecycle_idempotency_key' => $key,
                    'lifecycle_note' => $data['reason'] ?? null,
                ],
            );

            $original->forceFill([
                'status' => 'no',
                'rsvp_status' => 'no',
                'lifecycle_status' => 'replacement_assigned',
                'cancelled_at' => now(),
                'lifecycle_idempotency_key' => $key ? $key.'-original' : null,
                'lifecycle_note' => $data['reason'] ?? $original->lifecycle_note,
            ])->save();

            return $replacement->fresh();
        });
    }

    public function cancelEvent(Event $event, User $actor, ?string $reason = null, ?string $idempotencyKey = null): Event
    {
        return DB::transaction(function () use ($event, $actor, $reason, $idempotencyKey): Event {
            $lockedEvent = $this->lockedEvent($event);

            if ($lockedEvent->status !== 'cancelled') {
                $lockedEvent->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->id,
                    'cancellation_reason' => $reason,
                ])->save();
            }

            EventParticipant::query()
                ->where('event_id', $lockedEvent->id)
                ->whereIn('status', ['yes', 'late', 'waitlist'])
                ->lockForUpdate()
                ->get()
                ->each(function (EventParticipant $participant) use ($lockedEvent, $reason, $idempotencyKey): void {
                    if ($participant->lifecycle_status === 'event_cancelled'
                        && (! $idempotencyKey || $participant->lifecycle_idempotency_key === $idempotencyKey)) {
                        return;
                    }

                    $participant->forceFill([
                        'lifecycle_status' => 'event_cancelled',
                        'cancelled_at' => $participant->cancelled_at ?: now(),
                        'lifecycle_idempotency_key' => $idempotencyKey,
                        'lifecycle_note' => $reason,
                    ])->save();

                    $this->refundParticipant($lockedEvent, $participant, [
                        'reason' => $reason ?: 'event_cancelled',
                        'idempotency_key' => $idempotencyKey ? $idempotencyKey.'-'.$participant->id : null,
                    ]);
                });

            return $lockedEvent->fresh();
        });
    }

    private function lockedEvent(Event $event): Event
    {
        return Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
    }

    private function lockedParticipant(Event $event, User $user): EventParticipant
    {
        return EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function alreadyApplied(EventParticipant $participant, ?string $key, array $closedStatuses): bool
    {
        return $key
            && $participant->lifecycle_idempotency_key === $key
            && in_array($participant->lifecycle_status, $closedStatuses, true);
    }

    private function shouldRefund(EventParticipant $participant, array $data): bool
    {
        return (bool) ($data['refund'] ?? true)
            && $participant->payment_id
            && ! $participant->refund_payment_id;
    }

    private function refundParticipant(Event $event, EventParticipant $participant, array $data): ?Payment
    {
        if (! $participant->payment_id || $participant->refund_payment_id) {
            return $participant->refundPayment;
        }

        $payment = Payment::query()->whereKey($participant->payment_id)->lockForUpdate()->first();

        if (! $payment || (float) $payment->amount <= 0.0) {
            return null;
        }

        $refund = Payment::query()->create([
            'club_id' => $payment->club_id ?: $event->club_id ?: $event->team?->club_id,
            'user_id' => $participant->user_id,
            'invoice_id' => $payment->invoice_id,
            'purpose' => 'event_refund',
            'amount' => -1 * (float) $payment->amount,
            'status' => 'refunded',
            'method' => $payment->method ?: 'internal',
            'reference' => $this->refundReference($event, $participant, $data),
            'paid_at' => now(),
            'notes' => $data['reason'] ?? null,
        ]);

        $participant->forceFill([
            'lifecycle_status' => $participant->lifecycle_status === 'event_cancelled' ? 'event_cancelled' : 'refunded',
            'refund_payment_id' => $refund->id,
            'refunded_at' => now(),
        ])->save();

        return $refund;
    }

    private function refundReference(Event $event, EventParticipant $participant, array $data): string
    {
        $key = $data['idempotency_key'] ?? 'participant-'.$participant->id;

        return 'EVT-'.$event->id.'-REF-'.substr(hash('sha256', $key), 0, 16);
    }

    private function cancelEventIfBelowMinimum(Event $event, array $data): void
    {
        if (! $event->min_participants) {
            return;
        }

        $accepted = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', 'yes')
            ->whereNotIn('lifecycle_status', ['cancelled', 'refunded', 'replacement_assigned', 'event_cancelled'])
            ->count();

        if ($accepted >= $event->min_participants || $event->status === 'cancelled') {
            return;
        }

        $event->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $data['minimum_reason'] ?? 'minimum_participants_not_reached',
        ])->save();
    }
}
