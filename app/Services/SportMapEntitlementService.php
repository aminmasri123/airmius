<?php

namespace App\Services;

use App\Models\ExternalProviderUsageEvent;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SportMapEntitlementService
{
    private const PREMIUM_SLUGS = ['sportler-pro', 'trainer-pro', 'club', 'pro', 'elite'];

    public function capabilities(User $user): array
    {
        $routeGeneration = $this->routeGeneration($user);

        return [
            'map_view' => [
                'available' => true,
                'label' => 'Sportkarte',
                'reason' => null,
            ],
            'tracking' => [
                'available' => true,
                'label' => 'Tracking Basis',
                'reason' => null,
            ],
            'sport_places' => [
                'available' => true,
                'label' => 'Sportplätze finden und eintragen',
                'reason' => null,
            ],
            'route_generation' => $routeGeneration,
        ];
    }

    public function ensureCanGenerateRoute(User $user): void
    {
        $access = $this->routeGeneration($user);

        if (! $access['available']) {
            throw ValidationException::withMessages([
                'route_generation' => $access['reason'],
            ]);
        }
    }

    public function routeGeneration(User $user): array
    {
        $tier = $this->routeGenerationTier($user);
        $used = $this->monthlyRouteGenerationUsage($user);
        $remaining = $tier['monthly_limit'] === null ? null : max(0, $tier['monthly_limit'] - $used);
        $available = $tier['monthly_limit'] === null || $remaining > 0;

        return [
            'available' => $available,
            'label' => $tier['label'],
            'tier' => $tier['key'],
            'monthly_limit' => $tier['monthly_limit'],
            'monthly_used' => $used,
            'monthly_remaining' => $remaining,
            'requires_premium' => $tier['key'] === 'free',
            'reason' => $available
                ? null
                : "Dein monatliches Limit für automatisch generierte Routen ({$tier['monthly_limit']}x) ist erreicht. Es wird nächsten Monat automatisch zurückgesetzt.",
        ];
    }

    private function routeGenerationTier(User $user): array
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return [
                'key' => 'admin',
                'label' => 'Admin',
                'monthly_limit' => null,
            ];
        }

        if ($this->activeSubscriptionSlugs($user)->intersect(self::PREMIUM_SLUGS)->isNotEmpty()) {
            return [
                'key' => 'premium',
                'label' => 'Pro Routing',
                'monthly_limit' => 150,
            ];
        }

        return [
            'key' => 'free',
            'label' => 'Free Routing',
            'monthly_limit' => 10,
        ];
    }

    private function activeSubscriptionSlugs(User $user): Collection
    {
        $userSlugs = $user->subscriptions()
            ->grantingAccess()
            ->with('plan:id,slug')
            ->get()
            ->pluck('plan.slug')
            ->filter();

        $clubSlugs = $user->clubs()
            ->with(['currentSubscription.plan:id,slug'])
            ->whereHas('currentSubscription', function ($query) {
                $query
                    ->grantingAccess()
                    ->whereHas('plan');
            })
            ->get()
            ->pluck('currentSubscription.plan.slug')
            ->filter();

        return $userSlugs->merge($clubSlugs)->unique()->values();
    }

    private function monthlyRouteGenerationUsage(User $user): int
    {
        return (int) ExternalProviderUsageEvent::query()
            ->where('user_id', $user->id)
            ->where('area', 'routing')
            ->where('service', 'directions')
            ->where('operation', 'route')
            ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }
}
