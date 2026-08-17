<?php

namespace App\Services\Subscriptions;

use App\Models\ClubSubscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionPaymentIssue;
use App\Notifications\SubscriptionRenewed;
use App\Notifications\SubscriptionResumed;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SubscriptionLifecycleService
{
    public function __construct(private readonly SubscriptionProviderSyncService $providerSync) {}

    public function canCancel(ClubSubscription|UserSubscription $subscription): bool
    {
        return ! in_array($subscription->status, ['cancelled', 'cancels_at_period_end'], true);
    }

    public function cancel(ClubSubscription|UserSubscription $subscription, string $mode = 'period_end'): bool
    {
        if (! in_array($mode, ['period_end', 'now'], true)) {
            throw new InvalidArgumentException("Unsupported subscription cancellation mode [{$mode}].");
        }

        $subscription->refresh();

        if (! $this->needsCancellation($subscription, $mode)) {
            return false;
        }

        $this->providerSync->cancel($subscription, $mode);

        $changed = DB::transaction(function () use ($subscription, $mode): bool {
            $locked = $subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if (! $this->needsCancellation($locked, $mode)) {
                return false;
            }

            $now = now();
            $updates = $mode === 'now'
                ? [
                    'status' => 'cancelled',
                    'cancel_at_period_end' => false,
                    'cancels_at' => $now,
                    'cancelled_at' => $now,
                    'current_period_ends_at' => $now,
                    'next_invoice_at' => null,
                    'grace_period_ends_at' => null,
                    'access_restricted_at' => null,
                ]
                : [
                    'status' => 'cancels_at_period_end',
                    'cancel_at_period_end' => true,
                    'cancels_at' => $this->contractualCancellationDate($locked, $now),
                    'cancelled_at' => $now,
                    'next_invoice_at' => null,
                    'grace_period_ends_at' => null,
                    'access_restricted_at' => null,
                ];

            $locked->forceFill([
                ...$updates,
                'cancellation_email_sent_at' => null,
            ])->save();

            return true;
        });

        if (! $changed) {
            return false;
        }

        $subscription->refresh();
        $this->notifyCancellation($subscription, $mode);
        $this->sendSubscriptionEmail(
            $subscription,
            new SubscriptionCancelled($subscription, $mode),
            'cancellation_email_sent_at',
        );

        return true;
    }

    public function renew(ClubSubscription|UserSubscription $subscription, int $months): bool
    {
        if ($months < 1 || $months > 36) {
            throw new InvalidArgumentException('Subscription renewal months must be between 1 and 36.');
        }

        $subscription->refresh();
        $wasCancelling = $subscription->status === 'cancels_at_period_end'
            || $subscription->status === 'cancelled'
            || $subscription->cancel_at_period_end;

        if ($wasCancelling) {
            $this->providerSync->reinstate($subscription);
        }

        DB::transaction(function () use ($subscription, $months): void {
            $locked = $subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $baseDate = $locked->current_period_ends_at && $locked->current_period_ends_at->isFuture()
                ? $locked->current_period_ends_at
                : now();
            $endsAt = $baseDate->copy()->addMonths($months);

            $locked->forceFill([
                'status' => 'active',
                'cancel_at_period_end' => false,
                'cancels_at' => null,
                'cancelled_at' => null,
                'current_period_ends_at' => $endsAt,
                'next_invoice_at' => $endsAt,
                'grace_period_ends_at' => null,
                'access_restricted_at' => null,
                'last_renewed_at' => now(),
                'renewal_notified_at' => null,
                'cancellation_email_sent_at' => null,
                'renewal_email_sent_at' => null,
                'payment_issue_email_sent_at' => null,
            ])->save();
        });

        $subscription->refresh();
        $this->notifyRenewal($subscription);
        $this->sendSubscriptionEmail(
            $subscription,
            new SubscriptionRenewed($subscription),
            'renewal_email_sent_at',
        );

        return true;
    }

    public function resume(ClubSubscription|UserSubscription $subscription): bool
    {
        $subscription->refresh();

        if ($subscription->status !== 'cancels_at_period_end'
            || ($subscription->cancels_at && $subscription->cancels_at->isPast())) {
            return false;
        }

        if (! $this->providerSync->reinstate($subscription)) {
            return false;
        }

        $changed = DB::transaction(function () use ($subscription): bool {
            $locked = $subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($locked->status !== 'cancels_at_period_end'
                || ($locked->cancels_at && $locked->cancels_at->isPast())) {
                return false;
            }

            $locked->forceFill([
                'status' => 'active',
                'cancel_at_period_end' => false,
                'cancels_at' => null,
                'cancelled_at' => null,
                'next_invoice_at' => $locked->current_period_ends_at,
                'grace_period_ends_at' => null,
                'access_restricted_at' => null,
                'cancellation_email_sent_at' => null,
                'renewal_email_sent_at' => null,
                'payment_issue_email_sent_at' => null,
            ])->save();

            return true;
        });

        if (! $changed) {
            return false;
        }

        $subscription->refresh();
        $this->notifyResume($subscription);
        $this->sendSubscriptionEmail(
            $subscription,
            new SubscriptionResumed($subscription),
            'renewal_email_sent_at',
        );

        return true;
    }

    public function finalizeCancellation(ClubSubscription|UserSubscription $subscription): bool
    {
        $changed = DB::transaction(function () use ($subscription): bool {
            $locked = $subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($locked->status !== 'cancels_at_period_end'
                || ! $locked->cancels_at
                || $locked->cancels_at->isFuture()) {
                return false;
            }

            $locked->forceFill([
                'status' => 'cancelled',
                'cancel_at_period_end' => false,
                'cancelled_at' => now(),
                'next_invoice_at' => null,
                'grace_period_ends_at' => null,
                'access_restricted_at' => null,
            ])->save();

            return true;
        });

        if (! $changed) {
            return false;
        }

        $subscription->refresh();
        $this->notifyEnded($subscription);
        $this->sendSubscriptionEmail(
            $subscription,
            new SubscriptionCancelled($subscription, 'now'),
            'cancellation_email_sent_at',
        );

        return true;
    }

    public function sendCurrentStatusEmail(ClubSubscription|UserSubscription $subscription): void
    {
        $subscription->refresh();

        if (in_array($subscription->status, ['cancelled', 'cancels_at_period_end'], true)) {
            $this->sendSubscriptionEmail(
                $subscription,
                new SubscriptionCancelled(
                    $subscription,
                    $subscription->status === 'cancelled' ? 'now' : 'period_end',
                ),
                'cancellation_email_sent_at',
            );
        }

        if ($subscription->status === 'past_due') {
            $this->sendSubscriptionEmail(
                $subscription,
                new SubscriptionPaymentIssue($subscription),
                'payment_issue_email_sent_at',
            );
        }
    }

    public function notifyAdministrativeUpdate(
        ClubSubscription|UserSubscription $subscription,
        ?User $actor = null,
    ): void {
        $subscription->refresh()->loadMissing('plan');
        $isClub = $subscription instanceof ClubSubscription;
        $clubName = $isClub
            ? ($subscription->club?->name ?? AppNotification::translatedReplacement(
                'subscription.notifications.club_fallback',
                'Club',
            ))
            : '';
        $status = AppNotification::translatedReplacement(
            'subscription.notifications.status_'.$subscription->status,
            str_replace('_', ' ', ucfirst((string) $subscription->status)),
        );
        $stateHash = hash('sha256', implode('|', [
            $subscription->subscription_plan_id,
            $subscription->status,
            $subscription->trial_ends_at?->toISOString(),
            $subscription->current_period_ends_at?->toISOString(),
            $subscription->payment_provider,
        ]));

        foreach ($this->recipients($subscription) as $recipient) {
            AppNotification::sendLocalized(
                $recipient,
                $isClub ? 'club.subscription.updated' : 'subscription.updated',
                'subscription.notifications.updated_title',
                $isClub
                    ? 'subscription.notifications.updated_club_body'
                    : 'subscription.notifications.updated_user_body',
                [
                    'club' => $clubName,
                    'plan' => $subscription->plan?->name ?? AppNotification::translatedReplacement(
                        'subscription.email.plan_fallback',
                        'Airmius plan',
                    ),
                    'status' => $status,
                ],
                [
                    'subscription_id' => $subscription->id,
                    'subscription_type' => $isClub ? 'club' : 'user',
                    'club_id' => $isClub ? $subscription->club_id : null,
                    'club' => $isClub ? $subscription->club_id : null,
                    'plan_id' => $subscription->subscription_plan_id,
                    'status' => $subscription->status,
                    'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                    'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                    'changed_by_user_id' => $actor?->id,
                    'url' => $isClub ? '/club-cockpit' : '/settings',
                    'deep_link' => $isClub
                        ? 'airmius://clubs/'.$subscription->club_id.'/billing'
                        : 'airmius://dashboard',
                ],
                [
                    'category' => 'billing',
                    'dedupe_key' => $this->eventKey(
                        $subscription,
                        'administrative-update',
                        $subscription->updated_at,
                        $stateHash,
                    ),
                ],
            );
        }
    }

    public function contractualCancellationDate(
        ClubSubscription|UserSubscription $subscription,
        ?Carbon $reference = null,
    ): Carbon {
        $subscription->loadMissing('plan');
        $reference ??= now();
        $periodEnd = ($subscription->current_period_ends_at ?: $reference)->copy();
        $noticeEnd = $reference->copy()->addDays((int) ($subscription->plan?->cancellation_notice_days ?? 0));
        $minimumTermEnd = $subscription->created_at
            ? $subscription->created_at->copy()->addMonths((int) ($subscription->plan?->minimum_term_months ?? 0))
            : $reference->copy();

        return collect([$periodEnd, $noticeEnd, $minimumTermEnd, $reference])
            ->sortByDesc(fn (Carbon $date) => $date->getTimestamp())
            ->first()
            ->copy();
    }

    private function needsCancellation(ClubSubscription|UserSubscription $subscription, string $mode): bool
    {
        if ($subscription->status === 'cancelled') {
            return false;
        }

        return $mode === 'now'
            || $subscription->status !== 'cancels_at_period_end'
            || ! $subscription->cancel_at_period_end;
    }

    private function notifyCancellation(ClubSubscription|UserSubscription $subscription, string $mode): void
    {
        $isClub = $subscription instanceof ClubSubscription;
        $titleKey = $mode === 'now'
            ? 'subscription.notifications.cancelled_now_title'
            : 'subscription.notifications.cancelled_period_end_title';
        $bodyKey = sprintf(
            'subscription.notifications.cancelled_%s_%s_body',
            $isClub ? 'club' : 'user',
            $mode === 'now' ? 'now' : 'period_end',
        );
        $replace = [
            'club' => $isClub
                ? ($subscription->club?->name ?? AppNotification::translatedReplacement(
                    'subscription.notifications.club_fallback',
                    'Club',
                ))
                : '',
            'date' => $subscription->cancels_at?->toDateString() ?? '-',
        ];

        foreach ($this->recipients($subscription) as $recipient) {
            AppNotification::sendLocalized(
                $recipient,
                $isClub ? 'club.subscription.cancelled' : 'subscription.cancelled',
                $titleKey,
                $bodyKey,
                $replace,
                [
                    'subscription_id' => $subscription->id,
                    'subscription_type' => $isClub ? 'club' : 'user',
                    'club_id' => $isClub ? $subscription->club_id : null,
                    'plan_id' => $subscription->subscription_plan_id,
                    'cancels_at' => $subscription->cancels_at?->toISOString(),
                ],
                [
                    'dedupe_key' => $this->eventKey(
                        $subscription,
                        'cancelled',
                        $subscription->cancelled_at,
                        $subscription->last_renewed_at?->format('Uu'),
                    ),
                    'priority' => 'high',
                ],
            );
        }
    }

    private function notifyRenewal(ClubSubscription|UserSubscription $subscription): void
    {
        $isClub = $subscription instanceof ClubSubscription;

        foreach ($this->recipients($subscription) as $recipient) {
            AppNotification::sendLocalized(
                $recipient,
                $isClub ? 'club.subscription.renewed' : 'subscription.renewed',
                'subscription.notifications.renewed_title',
                $isClub
                    ? 'subscription.notifications.renewed_club_body'
                    : 'subscription.notifications.renewed_user_body',
                [
                    'club' => $isClub
                        ? ($subscription->club?->name ?? AppNotification::translatedReplacement(
                            'subscription.notifications.club_fallback',
                            'Club',
                        ))
                        : '',
                    'date' => $subscription->current_period_ends_at?->toDateString() ?? '-',
                ],
                [
                    'subscription_id' => $subscription->id,
                    'subscription_type' => $isClub ? 'club' : 'user',
                    'club_id' => $isClub ? $subscription->club_id : null,
                    'plan_id' => $subscription->subscription_plan_id,
                    'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                ],
                [
                    'dedupe_key' => $this->eventKey(
                        $subscription,
                        'renewed',
                        $subscription->last_renewed_at,
                        $subscription->current_period_ends_at?->format('Uu'),
                    ),
                ],
            );
        }

        $subscription->forceFill(['renewal_notified_at' => now()])->save();
    }

    private function notifyResume(ClubSubscription|UserSubscription $subscription): void
    {
        $isClub = $subscription instanceof ClubSubscription;

        foreach ($this->recipients($subscription) as $recipient) {
            AppNotification::sendLocalized(
                $recipient,
                $isClub ? 'club.subscription.resumed' : 'subscription.resumed',
                'subscription.notifications.resumed_title',
                $isClub
                    ? 'subscription.notifications.resumed_club_body'
                    : 'subscription.notifications.resumed_user_body',
                [
                    'club' => $isClub
                        ? ($subscription->club?->name ?? AppNotification::translatedReplacement(
                            'subscription.notifications.club_fallback',
                            'Club',
                        ))
                        : '',
                    'date' => $subscription->next_invoice_at?->toDateString() ?? '-',
                ],
                [
                    'subscription_id' => $subscription->id,
                    'subscription_type' => $isClub ? 'club' : 'user',
                    'club_id' => $isClub ? $subscription->club_id : null,
                    'plan_id' => $subscription->subscription_plan_id,
                    'next_invoice_at' => $subscription->next_invoice_at?->toISOString(),
                ],
                [
                    'dedupe_key' => $this->eventKey(
                        $subscription,
                        'resumed',
                        $subscription->updated_at,
                        $subscription->next_invoice_at?->format('Uu'),
                    ),
                ],
            );
        }
    }

    private function notifyEnded(ClubSubscription|UserSubscription $subscription): void
    {
        $isClub = $subscription instanceof ClubSubscription;

        foreach ($this->recipients($subscription) as $recipient) {
            AppNotification::sendLocalized(
                $recipient,
                $isClub ? 'club.subscription.ended' : 'subscription.ended',
                'subscription.notifications.ended_title',
                $isClub
                    ? 'subscription.notifications.ended_club_body'
                    : 'subscription.notifications.ended_user_body',
                [
                    'club' => $isClub
                        ? ($subscription->club?->name ?? AppNotification::translatedReplacement(
                            'subscription.notifications.club_fallback',
                            'Club',
                        ))
                        : '',
                ],
                [
                    'subscription_id' => $subscription->id,
                    'subscription_type' => $isClub ? 'club' : 'user',
                    'club_id' => $isClub ? $subscription->club_id : null,
                    'plan_id' => $subscription->subscription_plan_id,
                ],
                [
                    'dedupe_key' => $this->eventKey(
                        $subscription,
                        'ended',
                        $subscription->cancelled_at,
                        $subscription->cancels_at?->format('Uu'),
                    ),
                    'priority' => 'high',
                ],
            );
        }
    }

    private function sendSubscriptionEmail(
        ClubSubscription|UserSubscription $subscription,
        Notification $notification,
        string $sentColumn,
    ): void {
        if ($subscription->{$sentColumn}) {
            return;
        }

        foreach ($this->recipients($subscription) as $recipient) {
            if (filled($recipient->email)) {
                $recipient->notify($notification);
            }
        }

        $subscription->forceFill([$sentColumn => now()])->save();
    }

    /** @return Collection<int, User> */
    private function recipients(ClubSubscription|UserSubscription $subscription): Collection
    {
        if ($subscription instanceof UserSubscription) {
            $subscription->loadMissing('user');

            return $subscription->user ? collect([$subscription->user]) : collect();
        }

        $subscription->loadMissing(['club.owner', 'plan']);
        $club = $subscription->club;

        if (! $club) {
            return collect();
        }

        return ClubRoles::whereAny($club->users(), ClubRoles::SUBSCRIPTION_MANAGERS)
            ->get()
            ->push($club->owner)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function eventKey(
        ClubSubscription|UserSubscription $subscription,
        string $event,
        mixed $occurredAt,
        ?string $context = null,
    ): string {
        return implode(':', [
            'subscription',
            $subscription instanceof ClubSubscription ? 'club' : 'user',
            $subscription->id,
            $event,
            $occurredAt?->format('Uu') ?? now()->format('Uu'),
            $context ?? 'initial',
        ]);
    }
}
