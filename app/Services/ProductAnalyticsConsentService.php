<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use App\Support\MinorSafety;

class ProductAnalyticsConsentService
{
    /**
     * @return array<string, mixed>
     */
    public function updates(User $user, array $validated): array
    {
        $enabled = array_key_exists('product_analytics_consent', $validated)
            ? (bool) $validated['product_analytics_consent']
            : (bool) $user->product_analytics_consent;

        if (MinorSafety::isUnderConsentAge($user)) {
            $enabled = false;
        }

        $updates = ['product_analytics_consent' => $enabled];

        if ($enabled && ! $user->product_analytics_consent) {
            $updates['product_analytics_consented_at'] = now();
            $updates['product_analytics_consent_version'] = (string) config('product_analytics.consent_version');
        }

        return $updates;
    }

    public function auditIfChanged(User $user, bool $previous): void
    {
        $current = (bool) $user->product_analytics_consent;

        if ($previous === $current) {
            return;
        }

        Activity::create([
            'user_id' => $user->id,
            'type' => 'privacy.consent_updated',
            'data' => [
                'consent' => 'product_analytics',
                'enabled' => $current,
                'version' => $current ? $user->product_analytics_consent_version : null,
                'changed_at' => now()->toJSON(),
            ],
        ]);
    }
}
