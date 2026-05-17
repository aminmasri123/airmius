<?php

namespace App\Services;

use App\Models\UserSubscription;

class UserSubscriptionActivationService
{
    private const ACTIVE_ACCESS_STATUSES = ['active', 'trialing'];
    private const RETIRED_BY_REPLACEMENT_STATUSES = ['active', 'trialing', 'past_due', 'cancels_at_period_end'];

    public function retireOtherUserSubscriptions(UserSubscription $subscription): void
    {
        if (! in_array($subscription->status, self::ACTIVE_ACCESS_STATUSES, true)) {
            return;
        }

        $subscription->loadMissing('plan');
        $targetActor = $subscription->plan?->target_actor;

        if (! $targetActor || ! $subscription->user_id || ! $subscription->id) {
            return;
        }

        UserSubscription::query()
            ->where('user_id', $subscription->user_id)
            ->whereKeyNot($subscription->id)
            ->whereIn('status', self::RETIRED_BY_REPLACEMENT_STATUSES)
            ->whereHas('plan', fn ($query) => $query->where('target_actor', $targetActor))
            ->update([
                'status' => 'cancelled',
                'cancel_at_period_end' => false,
                'cancels_at' => null,
                'cancelled_at' => now(),
                'grace_period_ends_at' => null,
                'access_restricted_at' => null,
            ]);
    }
}
