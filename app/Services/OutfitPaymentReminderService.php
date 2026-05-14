<?php

namespace App\Services;

use App\Models\OutfitSubscription;
use App\Notifications\OutfitPaymentDunningNotice;
use App\Notifications\OutfitPaymentExpired;
use App\Notifications\OutfitPaymentReminder;
use App\Support\AppNotification;

class OutfitPaymentReminderService
{
    public const MAX_REMINDERS = 3;
    public const REMINDER_INTERVAL_DAYS = 3;
    public const EXPIRATION_DAYS = 9;
    public const MAX_DUNNING_LEVEL = 3;
    public const DUNNING_INTERVAL_DAYS = 7;

    public function canSendManualReminder(OutfitSubscription $subscription): bool
    {
        return $this->isReminderCandidate($subscription)
            && (int) $subscription->payment_reminders_sent < self::MAX_REMINDERS;
    }

    public function isDueForAutomaticReminder(OutfitSubscription $subscription): bool
    {
        if (! $this->canSendManualReminder($subscription)) {
            return false;
        }

        $referenceDate = $subscription->last_payment_reminder_sent_at ?: $subscription->created_at;

        return $referenceDate
            ? $referenceDate->lte(now()->subDays(self::REMINDER_INTERVAL_DAYS))
            : true;
    }

    public function send(OutfitSubscription $subscription): bool
    {
        if (! $this->canSendManualReminder($subscription)) {
            return false;
        }

        $subscription->loadMissing(['user', 'plan']);
        $user = $subscription->user;

        if (! $user) {
            return false;
        }

        AppNotification::send($user, 'outfit.payment.reminder', [
            'title' => 'Zahlung fuer Outfit-Abo offen',
            'message' => 'Bitte bezahle dein Outfit-Abo '.$this->planName($subscription).', damit wir deine Box vorbereiten koennen.',
            'subscription_id' => $subscription->id,
            'payment_reference' => $subscription->payment_reference,
            'amount_cents' => max(0, (int) $subscription->monthly_price_cents - (int) $subscription->sponsor_discount_cents),
            'currency' => $subscription->currency,
            'url' => route('auth.outfit-subscriptions.index'),
        ]);

        if ($user->email) {
            $user->notify(new OutfitPaymentReminder($subscription));
        }

        $subscription->forceFill([
            'payment_reminders_sent' => (int) $subscription->payment_reminders_sent + 1,
            'last_payment_reminder_sent_at' => now(),
        ])->save();

        return true;
    }

    public function isExpiredUnpaid(OutfitSubscription $subscription): bool
    {
        if (! $this->isReminderCandidate($subscription)) {
            return false;
        }

        if ((int) $subscription->payment_reminders_sent < self::MAX_REMINDERS) {
            return false;
        }

        return $subscription->created_at
            ? $subscription->created_at->lte(now()->subDays(self::EXPIRATION_DAYS))
            : false;
    }

    public function expire(OutfitSubscription $subscription): bool
    {
        if (! $this->isExpiredUnpaid($subscription)) {
            return false;
        }

        $subscription->loadMissing(['user', 'plan']);
        $user = $subscription->user;

        if ($user) {
            AppNotification::send($user, 'outfit.payment.expired', [
                'title' => 'Outfit-Abo Anfrage geloescht',
                'message' => 'Deine Outfit-Abo Anfrage '.$this->planName($subscription).' wurde geloescht, weil nach 9 Tagen keine Zahlung eingegangen ist.',
                'subscription_id' => $subscription->id,
                'payment_reference' => $subscription->payment_reference,
                'url' => route('auth.outfit-subscriptions.index'),
            ]);

            if ($user->email) {
                $user->notify(new OutfitPaymentExpired($subscription));
            }
        }

        $subscription->delete();

        return true;
    }

    public function isDunningCandidate(OutfitSubscription $subscription): bool
    {
        return in_array($subscription->status, ['active', 'payment_paused'], true)
            && in_array($subscription->payment_status, ['pending', 'failed'], true)
            && (int) $subscription->dunning_level < self::MAX_DUNNING_LEVEL
            && $subscription->payment_due_at
            && $subscription->payment_due_at->lte(now());
    }

    public function isDueForAutomaticDunning(OutfitSubscription $subscription): bool
    {
        if (! $this->isDunningCandidate($subscription)) {
            return false;
        }

        $referenceDate = $subscription->last_dunning_sent_at ?: $subscription->payment_due_at;

        return $referenceDate
            ? $referenceDate->lte(now()->subDays(self::DUNNING_INTERVAL_DAYS))
            : true;
    }

    public function sendDunning(OutfitSubscription $subscription): bool
    {
        if (! $this->isDunningCandidate($subscription)) {
            return false;
        }

        $subscription->loadMissing(['user', 'plan']);
        $user = $subscription->user;

        if (! $user) {
            return false;
        }

        $nextLevel = min(self::MAX_DUNNING_LEVEL, (int) $subscription->dunning_level + 1);
        $isFinal = $nextLevel >= self::MAX_DUNNING_LEVEL;

        AppNotification::send($user, 'outfit.payment.dunning', [
            'title' => $isFinal ? 'Letzte Mahnung fuer Outfit-Abo' : $nextLevel.'. Mahnung fuer Outfit-Abo',
            'message' => $isFinal
                ? 'Dein Outfit-Abo '.$this->planName($subscription).' wurde bis zum Zahlungseingang pausiert.'
                : 'Fuer dein Outfit-Abo '.$this->planName($subscription).' ist eine Zahlung offen.',
            'subscription_id' => $subscription->id,
            'dunning_level' => $nextLevel,
            'payment_reference' => $subscription->payment_reference,
            'url' => route('auth.outfit-subscriptions.index'),
        ]);

        if ($user->email) {
            $user->notify(new OutfitPaymentDunningNotice($subscription, $nextLevel));
        }

        $updates = [
            'dunning_level' => $nextLevel,
            'last_dunning_sent_at' => now(),
        ];

        if ($isFinal) {
            $updates['status'] = 'payment_paused';
            $updates['next_delivery_at'] = null;
            $updates['payment_paused_at'] = now();
            $updates['payment_paused_reason'] = 'Automatisch pausiert nach letzter Mahnung wegen offener Zahlung.';
        }

        $subscription->forceFill($updates)->save();

        return true;
    }

    public function resetDunningAfterPayment(OutfitSubscription $subscription): void
    {
        $subscription->forceFill([
            'status' => 'active',
            'payment_status' => 'paid',
            'dunning_level' => 0,
            'last_dunning_sent_at' => null,
            'payment_paused_at' => null,
            'payment_paused_reason' => null,
            'payment_due_at' => null,
            'next_delivery_at' => $subscription->next_delivery_at ?? now()->addMonth()->startOfDay(),
            'current_period_ends_at' => $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
                ? $subscription->current_period_ends_at->copy()->addMonthNoOverflow()
                : now()->addMonthNoOverflow(),
            'cancelled_at' => null,
        ])->save();
    }

    private function isReminderCandidate(OutfitSubscription $subscription): bool
    {
        return $subscription->status === 'pending_payment'
            && in_array($subscription->payment_status, ['pending', 'failed'], true);
    }

    private function planName(OutfitSubscription $subscription): string
    {
        return $subscription->plan?->name ? '"'.$subscription->plan->name.'"' : '';
    }
}
