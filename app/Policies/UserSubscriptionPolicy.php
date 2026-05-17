<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserSubscription;

class UserSubscriptionPolicy extends BasePolicy
{
    private const PORTAL_STATUSES = ['trialing', 'active', 'past_due', 'cancels_at_period_end'];

    public function cancel(User $user, UserSubscription $subscription): bool
    {
        return $subscription->user_id === $user->id;
    }

    public function accessProviderPortal(User $user, UserSubscription $subscription): bool
    {
        return $subscription->user_id === $user->id
            && $subscription->payment_provider === 'stripe'
            && in_array($subscription->status, self::PORTAL_STATUSES, true)
            && filled($subscription->provider_customer_id);
    }
}
