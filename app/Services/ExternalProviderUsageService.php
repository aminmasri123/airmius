<?php

namespace App\Services;

use App\Models\ExternalProviderUsageEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExternalProviderUsageService
{
    public function record(
        string $area,
        string $provider,
        string $service,
        string $operation,
        ?User $user = null,
        int $billableQuantity = 1,
        int $inputTokens = 0,
        int $outputTokens = 0,
        string $status = 'ok',
        array $metadata = [],
    ): void {
        try {
            ExternalProviderUsageEvent::query()->create([
                'user_id' => $user?->id,
                'area' => strtolower($area),
                'provider' => strtolower($provider),
                'service' => strtolower($service),
                'operation' => strtolower($operation),
                'billable_quantity' => max(0, $billableQuantity),
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'status' => substr($status, 0, 40),
                'metadata' => $metadata === [] ? null : $metadata,
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // Provider monitoring must never break the user-facing feature.
        }
    }

    public function dashboard(?string $month = null): array
    {
        $period = $this->period($month);
        $baseQuery = ExternalProviderUsageEvent::query()
            ->whereBetween('occurred_at', [$period['start'], $period['end']]);

        $usageRows = $this->usageRows(clone $baseQuery);
        $metrics = $this->metrics(clone $baseQuery);
        $comparisons = $this->comparisons($metrics);
        $recommendations = $this->recommendations($metrics, $comparisons);

        return [
            'period' => [
                'month' => $period['start']->format('Y-m'),
                'label' => $period['start']->locale('de')->isoFormat('MMMM YYYY'),
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
            ],
            'totals' => [
                'events' => (int) (clone $baseQuery)->count(),
                'users' => (int) (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'estimated_cost_eur' => round(collect($comparisons)->sum(fn ($comparison) => (float) ($comparison['current_plan']['estimated_cost_eur'] ?? 0)), 2),
            ],
            'cards' => $this->cards($metrics),
            'usageRows' => $usageRows,
            'comparisons' => $comparisons,
            'recommendations' => $recommendations,
            'assumptions' => [
                'usd_to_eur' => (float) config('provider_costs.usd_to_eur', 0.92),
                'self_hosted_monthly_eur' => (float) config('provider_costs.self_hosted_monthly_eur', 180),
                'note' => 'Kosten sind Schätzwerte. Admins sollten Providerpreise und Verträge regelmäßig aktualisieren.',
            ],
        ];
    }

    private function period(?string $month): array
    {
        $start = $month
            ? CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return [
            'start' => $start,
            'end' => $start->endOfMonth()->endOfDay(),
        ];
    }

    private function usageRows(Builder $query): array
    {
        return $query
            ->select([
                'area',
                'provider',
                'service',
                'operation',
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(billable_quantity) as billable_quantity'),
                DB::raw('SUM(input_tokens) as input_tokens'),
                DB::raw('SUM(output_tokens) as output_tokens'),
                DB::raw('COUNT(DISTINCT user_id) as users'),
                DB::raw('MAX(occurred_at) as last_seen_at'),
            ])
            ->groupBy('area', 'provider', 'service', 'operation')
            ->orderByDesc('requests')
            ->get()
            ->map(fn ($row) => [
                'area' => $row->area,
                'provider' => $row->provider,
                'service' => $row->service,
                'operation' => $row->operation,
                'requests' => (int) $row->requests,
                'billable_quantity' => (int) $row->billable_quantity,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'users' => (int) $row->users,
                'last_seen_at' => $row->last_seen_at,
            ])
            ->values()
            ->all();
    }

    private function metrics(Builder $query): array
    {
        $events = $query->get([
            'area',
            'provider',
            'service',
            'operation',
            'billable_quantity',
            'input_tokens',
            'output_tokens',
            'user_id',
        ]);

        $sumWhere = fn (callable $filter, string $field = 'billable_quantity') => (int) $events
            ->filter($filter)
            ->sum($field);

        $usersWhere = fn (callable $filter) => $events
            ->filter($filter)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->count();

        return [
            'map_loads' => $sumWhere(fn ($event) => $event->area === 'maps' && $event->operation === 'web_map_load'),
            'routing_requests' => $sumWhere(fn ($event) => $event->area === 'routing' && $event->service === 'directions'),
            'routing_users' => $usersWhere(fn ($event) => $event->area === 'routing' && $event->service === 'directions'),
            'navigation_trips' => $sumWhere(fn ($event) => $event->area === 'navigation' && $event->operation === 'trip'),
            'navigation_users' => $usersWhere(fn ($event) => $event->area === 'navigation'),
            'ai_text_requests' => $sumWhere(fn ($event) => $event->area === 'ai' && $event->service === 'text'),
            'ai_input_tokens' => $sumWhere(fn ($event) => $event->area === 'ai' && $event->service === 'text', 'input_tokens'),
            'ai_output_tokens' => $sumWhere(fn ($event) => $event->area === 'ai' && $event->service === 'text', 'output_tokens'),
            'ai_image_requests' => $sumWhere(fn ($event) => $event->area === 'ai' && $event->service === 'image'),
        ];
    }

    private function cards(array $metrics): array
    {
        return [
            $this->limitCard('Kartenaufrufe', $metrics['map_loads'], 50000, 'Map Loads', 'Kosten steigen, wenn sehr viele Nutzer die Sportkarte laden.'),
            $this->limitCard('Routenplanung', $metrics['routing_requests'], (int) config('provider_costs.routing_monthly_alert_limit', 15000), 'Credits/Requests', 'GraphHopper Free ist nur fuer Entwicklung gedacht. Gespeicherte Routen sparen Credits und Geld.'),
            $this->limitCard('Navigation', $metrics['navigation_trips'], 1000, 'Trips', 'Echte Live-Navigation kostet pro Nutzer und pro Trip.'),
            [
                'label' => 'KI-Nutzung',
                'value' => $metrics['ai_text_requests'],
                'limit' => null,
                'unit' => 'Text-Requests',
                'percent' => null,
                'status' => $metrics['ai_text_requests'] > 0 ? 'active' : 'empty',
                'hint' => 'Für Ernährung, Training und Blogtexte werden Tokens gezählt.',
            ],
        ];
    }

    private function limitCard(string $label, int $value, int $limit, string $unit, string $hint): array
    {
        $percent = $limit > 0 ? min(999, round(($value / $limit) * 100)) : 0;

        return [
            'label' => $label,
            'value' => $value,
            'limit' => $limit,
            'unit' => $unit,
            'percent' => $percent,
            'status' => $percent >= 90 ? 'critical' : ($percent >= 75 ? 'warning' : 'ok'),
            'hint' => $hint,
        ];
    }

    private function comparisons(array $metrics): array
    {
        return [
            $this->comparison('Webkarte', 'maps', $metrics['map_loads'], ['mapbox_map_loads', 'self_hosted_routing'], 'mapbox_map_loads'),
            $this->comparison('Routenplanung', 'routing', $metrics['routing_requests'], [
                'mapbox_directions',
                'graphhopper_basic',
                'graphhopper_standard',
                'openrouteservice_standard',
                'self_hosted_routing',
            ], 'graphhopper_basic'),
            $this->navigationComparison($metrics),
            $this->aiTextComparison($metrics),
            $this->comparison('KI-Bildanalyse', 'ai', $metrics['ai_image_requests'], ['google_vision_image', 'ionos_vision_image', 'openai_vision_image', 'ai_image'], 'google_vision_image'),
        ];
    }

    private function comparison(string $label, string $area, int $units, array $planKeys, string $currentPlanKey): array
    {
        $plans = collect($planKeys)
            ->map(fn ($key) => $this->planEstimate($key, $units))
            ->values()
            ->all();
        $currentPlan = collect($plans)->firstWhere('key', $currentPlanKey) ?? $plans[0] ?? null;
        $cheapest = collect($plans)
            ->filter(fn ($plan) => $plan['available'])
            ->sortBy('estimated_cost_eur')
            ->first();

        return [
            'label' => $label,
            'area' => $area,
            'units' => $units,
            'unit_label' => $currentPlan['unit_label'] ?? 'Einheiten',
            'current_plan' => $currentPlan,
            'cheapest_plan' => $cheapest,
            'plans' => $plans,
            'break_even' => $this->breakEven($currentPlanKey, 'self_hosted_routing'),
        ];
    }

    private function navigationComparison(array $metrics): array
    {
        $plan = $this->navigationEstimate('mapbox_navigation', $metrics['navigation_users'], $metrics['navigation_trips']);

        return [
            'label' => 'Live-Navigation',
            'area' => 'navigation',
            'units' => $metrics['navigation_trips'],
            'unit_label' => 'Trips',
            'current_plan' => $plan,
            'cheapest_plan' => $plan,
            'plans' => [$plan],
            'break_even' => null,
        ];
    }

    private function aiTextComparison(array $metrics): array
    {
        $plans = collect(['google_text', 'mistral_text', 'ionos_text', 'openai_text'])
            ->map(fn ($key) => $this->tokenEstimate($key, $metrics['ai_input_tokens'], $metrics['ai_output_tokens']))
            ->values()
            ->all();
        $current = $plans[0] ?? null;
        $cheapest = collect($plans)->sortBy('estimated_cost_eur')->first();

        return [
            'label' => 'KI-Text',
            'area' => 'ai',
            'units' => $metrics['ai_input_tokens'] + $metrics['ai_output_tokens'],
            'unit_label' => 'Tokens',
            'current_plan' => $current,
            'cheapest_plan' => $cheapest,
            'plans' => $plans,
            'break_even' => null,
        ];
    }

    private function planEstimate(string $key, int $units): array
    {
        $plan = config("provider_costs.plans.{$key}", []);

        if (($plan['kind'] ?? null) === 'fixed') {
            $included = (int) ($plan['included_units'] ?? 0);

            return [
                'key' => $key,
                'label' => $plan['label'] ?? $key,
                'provider' => $plan['provider'] ?? 'unknown',
                'unit_label' => $plan['unit_label'] ?? 'Einheiten',
                'estimated_cost_eur' => round((float) ($plan['monthly_eur'] ?? 0), 2),
                'projected_2x_eur' => round((float) ($plan['monthly_eur'] ?? 0), 2),
                'projected_5x_eur' => round((float) ($plan['monthly_eur'] ?? 0), 2),
                'included_units' => $included,
                'available' => $included === 0 || $units <= $included,
                'risk' => $plan['risk'] ?? ($included > 0 && $units > $included ? 'Volumen Über Planannahme. Upgrade oder Vertrag prüfen.' : null),
            ];
        }

        $cost = $this->meteredCost($plan, $units);

        return [
            'key' => $key,
            'label' => $plan['label'] ?? $key,
            'provider' => $plan['provider'] ?? 'unknown',
            'unit_label' => $plan['unit_label'] ?? 'Einheiten',
            'estimated_cost_eur' => $cost,
            'projected_2x_eur' => $this->meteredCost($plan, $units * 2),
            'projected_5x_eur' => $this->meteredCost($plan, $units * 5),
            'free_units' => (int) ($plan['free_units'] ?? 0),
            'available' => true,
            'risk' => $plan['risk'] ?? null,
        ];
    }

    private function navigationEstimate(string $key, int $users, int $trips): array
    {
        $plan = config("provider_costs.plans.{$key}", []);
        $userCost = max(0, $users - (int) ($plan['free_users'] ?? 0)) * (float) ($plan['price_per_user_usd'] ?? 0);
        $tripCost = $this->tieredCost(max(0, $trips - (int) ($plan['free_trips'] ?? 0)), $plan['trip_tiers'] ?? [], 'price_per_trip_usd', 1);
        $total = ($userCost + $tripCost) * (float) config('provider_costs.usd_to_eur', 0.92);

        return [
            'key' => $key,
            'label' => $plan['label'] ?? $key,
            'provider' => $plan['provider'] ?? 'unknown',
            'unit_label' => 'Trips + aktive Nutzer',
            'estimated_cost_eur' => round($total, 2),
            'projected_2x_eur' => round($this->navigationProjectedCost($plan, $users * 2, $trips * 2), 2),
            'projected_5x_eur' => round($this->navigationProjectedCost($plan, $users * 5, $trips * 5), 2),
            'free_units' => (int) ($plan['free_trips'] ?? 0),
            'available' => true,
            'risk' => 'Nur aktivieren, wenn echte Turn-by-turn Navigation gebraucht wird.',
        ];
    }

    private function navigationProjectedCost(array $plan, int $users, int $trips): float
    {
        $userCost = max(0, $users - (int) ($plan['free_users'] ?? 0)) * (float) ($plan['price_per_user_usd'] ?? 0);
        $tripCost = $this->tieredCost(max(0, $trips - (int) ($plan['free_trips'] ?? 0)), $plan['trip_tiers'] ?? [], 'price_per_trip_usd', 1);

        return ($userCost + $tripCost) * (float) config('provider_costs.usd_to_eur', 0.92);
    }

    private function tokenEstimate(string $key, int $inputTokens, int $outputTokens): array
    {
        $plan = config("provider_costs.plans.{$key}", []);
        $cost = (($inputTokens / 1000000) * (float) ($plan['input_per_million_eur'] ?? 0))
            + (($outputTokens / 1000000) * (float) ($plan['output_per_million_eur'] ?? 0));

        return [
            'key' => $key,
            'label' => $plan['label'] ?? $key,
            'provider' => $plan['provider'] ?? 'unknown',
            'unit_label' => 'Tokens',
            'estimated_cost_eur' => round($cost, 2),
            'projected_2x_eur' => round($cost * 2, 2),
            'projected_5x_eur' => round($cost * 5, 2),
            'available' => true,
            'risk' => $plan['provider'] === 'openai'
                ? 'Nur mit passendem AV-Vertrag, Datenminimierung und klarer Einwilligung für sensible Fitness-/Ernährungsdaten.'
                : 'EU-/DSGVO-Vertrag und Datenminimierung prüfen.',
        ];
    }

    private function meteredCost(array $plan, int $units): float
    {
        $paidUnits = max(0, $units - (int) ($plan['free_units'] ?? 0));
        $firstTier = $plan['tiers'][0] ?? [];
        $field = array_key_exists('price_per_1000_eur', $firstTier) ? 'price_per_1000_eur' : 'price_per_1000_usd';
        $cost = $this->tieredCost($paidUnits, $plan['tiers'] ?? [], $field, 1000);

        if ($field === 'price_per_1000_usd') {
            $cost *= (float) config('provider_costs.usd_to_eur', 0.92);
        }

        return round($cost, 2);
    }

    private function tieredCost(int $paidUnits, array $tiers, string $field, int $unitSize): float
    {
        $remaining = $paidUnits;
        $cursor = 0;
        $cost = 0.0;

        foreach ($tiers as $tier) {
            if ($remaining <= 0) {
                break;
            }

            $upTo = $tier['up_to'] ?? null;
            $tierCapacity = $upTo === null ? $remaining : max(0, (int) $upTo - $cursor);
            $used = min($remaining, $tierCapacity);
            $cost += ($used / $unitSize) * (float) ($tier[$field] ?? 0);
            $remaining -= $used;
            $cursor += $used;
        }

        return $cost;
    }

    private function breakEven(string $meteredKey, string $fixedKey): ?array
    {
        $metered = config("provider_costs.plans.{$meteredKey}", []);
        $fixed = config("provider_costs.plans.{$fixedKey}", []);

        if (($metered['kind'] ?? null) !== 'metered' || ($fixed['kind'] ?? null) !== 'fixed') {
            return null;
        }

        $target = (float) ($fixed['monthly_eur'] ?? 0);

        for ($units = 10000; $units <= 5000000; $units += 10000) {
            if ($this->meteredCost($metered, $units) >= $target) {
                return [
                    'units' => $units,
                    'target_cost_eur' => round($target, 2),
                    'message' => "Ab ca. {$units} Einheiten/Monat eigene Infrastruktur prüfen.",
                ];
            }
        }

        return null;
    }

    private function recommendations(array $metrics, array $comparisons): array
    {
        $items = [];

        if ($metrics['routing_requests'] >= 12000) {
            $items[] = [
                'level' => 'warning',
                'title' => 'GraphHopper Free/Dev wird eng',
                'body' => 'Ab etwa 12.000 Routing-Credits pro Monat solltest du auf GraphHopper Basic/Standard wechseln oder eigene Infrastruktur pruefen.',
            ];
        }

        if ($metrics['map_loads'] >= 40000) {
            $items[] = [
                'level' => 'warning',
                'title' => 'Kartenaufrufe steigen',
                'body' => 'Bei vielen Reloads oder hoher Nutzung lohnt sich Map-Caching, ein Tile-Anbieter-Vertrag oder eigene Vector-Tiles.',
            ];
        }

        if ($metrics['navigation_trips'] > 0) {
            $items[] = [
                'level' => 'info',
                'title' => 'Navigation SDK getrennt betrachten',
                'body' => 'Navigation kostet pro aktivem Nutzer und Trip. Erst für Pro-Funktionen oder echte Abbiegehinweise aktivieren.',
            ];
        }

        if ($metrics['ai_input_tokens'] + $metrics['ai_output_tokens'] > 0) {
            $items[] = [
                'level' => 'info',
                'title' => 'KI-Daten minimieren',
                'body' => 'Ernährung und Training können sensible Daten sein. Prompts anonymisieren, Einwilligung einholen und EU-Anbieter priorisieren.',
            ];
        }

        if ($items === []) {
            $items[] = [
                'level' => 'ok',
                'title' => 'Noch entspannt',
                'body' => 'Die aktuelle Nutzung liegt unter den Warnschwellen. Weiter messen und vor jeder Anbieterumstellung Preise aktualisieren.',
            ];
        }

        return $items;
    }
}
