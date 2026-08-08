<?php

namespace App\Services;

use App\Models\User;
use App\Support\MinorSafety;
use Illuminate\Database\Eloquent\Builder;

class ProductAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $days = null): array
    {
        $allowedWindows = array_map('intval', config('product_analytics.allowed_windows', [7, 28, 90]));
        $days = in_array($days, $allowedWindows, true) ? $days : 28;
        $minimumGroupSize = max(5, (int) config('product_analytics.minimum_group_size', 5));
        $since = now()->startOfDay()->subDays($days - 1);

        if (! config('product_analytics.enabled')) {
            return $this->emptyDashboard('disabled', $days, $since, $minimumGroupSize);
        }

        $eligibleUsers = $this->eligibleUsers();
        $cohortSize = (clone $eligibleUsers)->count();

        if ($cohortSize < $minimumGroupSize) {
            return $this->emptyDashboard('minimum_group', $days, $since, $minimumGroupSize);
        }

        $newUsers = (clone $eligibleUsers)
            ->where('users.created_at', '>=', $since)
            ->count();
        $activatedNewUsers = (clone $eligibleUsers)
            ->where('users.created_at', '>=', $since)
            ->where(fn (Builder $query) => $this->whereHasActivation($query))
            ->count();
        $activeUsers = (clone $eligibleUsers)
            ->where('users.last_seen_at', '>=', $since)
            ->count();
        $trainingUsers = (clone $eligibleUsers)
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('training_logs')
                ->whereColumn('training_logs.user_id', 'users.id')
                ->where(function ($query) use ($since) {
                    $query->where('training_logs.performed_at', '>=', $since)
                        ->orWhere(function ($query) use ($since) {
                            $query->whereNull('training_logs.performed_at')
                                ->where('training_logs.created_at', '>=', $since);
                        });
                }))
            ->count();
        $teamUsers = (clone $eligibleUsers)
            ->where('users.last_seen_at', '>=', $since)
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('team_user')
                ->whereColumn('team_user.user_id', 'users.id'))
            ->count();
        $paidCustomers = (clone $eligibleUsers)
            ->where(fn (Builder $query) => $this->whereIsPaidCustomer($query))
            ->count();
        $churnedCustomers = (clone $eligibleUsers)
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('user_subscriptions')
                ->join('subscription_plans', 'subscription_plans.id', '=', 'user_subscriptions.subscription_plan_id')
                ->whereColumn('user_subscriptions.user_id', 'users.id')
                ->whereNotNull('user_subscriptions.cancelled_at')
                ->where('user_subscriptions.cancelled_at', '>=', $since)
                ->where(function ($query) {
                    $query->where('subscription_plans.monthly_price_cents', '>', 0)
                        ->orWhere('subscription_plans.yearly_price_cents', '>', 0);
                }))
            ->count();

        $retentionCutoff = now()->subDays(7);
        $retentionCohort = (clone $eligibleUsers)
            ->where('users.created_at', '<=', $retentionCutoff)
            ->count();
        $retainedUsers = (clone $eligibleUsers)
            ->where('users.created_at', '<=', $retentionCutoff)
            ->where('users.last_seen_at', '>=', $retentionCutoff)
            ->count();

        return [
            'status' => 'ready',
            'period' => $this->period($days, $since),
            'privacy' => $this->privacyContract($minimumGroupSize, false, null),
            'metrics' => [
                $this->metric('consented_cohort', $cohortSize, null, $minimumGroupSize),
                $this->metric('activation', $activatedNewUsers, $newUsers, $minimumGroupSize),
                $this->metric('active_users', $activeUsers, $cohortSize, $minimumGroupSize),
                $this->metric('seven_day_retention', $retainedUsers, $retentionCohort, $minimumGroupSize),
                $this->metric('training_engagement', $trainingUsers, $cohortSize, $minimumGroupSize),
                $this->metric('team_engagement', $teamUsers, $cohortSize, $minimumGroupSize),
                $this->metric('paid_customers', $paidCustomers, $cohortSize, $minimumGroupSize),
                $this->metric('recent_churn', $churnedCustomers, $paidCustomers, $minimumGroupSize),
            ],
            'definitions' => [
                'activation' => 'new_consented_adult_with_operational_action',
                'active_users' => 'consented_adult_last_seen_in_period',
                'seven_day_retention' => 'established_consented_adult_seen_in_last_7_days',
                'training_engagement' => 'consented_adult_with_training_log_in_period',
                'team_engagement' => 'active_consented_adult_with_team_membership',
                'paid_customers' => 'consented_adult_with_completed_order_or_paid_active_plan',
                'recent_churn' => 'consented_adult_with_paid_plan_cancelled_in_period',
            ],
        ];
    }

    private function eligibleUsers(): Builder
    {
        $adultCutoff = now()->subYears(MinorSafety::CONSENT_AGE)->toDateString();

        return User::query()
            ->where('users.product_analytics_consent', true)
            ->whereNotNull('users.product_analytics_consented_at')
            ->whereNotNull('users.birth_date')
            ->whereDate('users.birth_date', '<=', $adultCutoff)
            ->whereNull('users.anonymized_at');
    }

    private function whereHasActivation(Builder $query): void
    {
        $query
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('training_logs')
                ->whereColumn('training_logs.user_id', 'users.id'))
            ->orWhereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('team_user')
                ->whereColumn('team_user.user_id', 'users.id'))
            ->orWhereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('club_user')
                ->whereColumn('club_user.user_id', 'users.id'))
            ->orWhereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('commerce_orders')
                ->whereColumn('commerce_orders.user_id', 'users.id')
                ->where('commerce_orders.status', 'completed'));
    }

    private function whereIsPaidCustomer(Builder $query): void
    {
        $query
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('commerce_orders')
                ->whereColumn('commerce_orders.user_id', 'users.id')
                ->where('commerce_orders.status', 'completed'))
            ->orWhereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('user_subscriptions')
                ->join('subscription_plans', 'subscription_plans.id', '=', 'user_subscriptions.subscription_plan_id')
                ->whereColumn('user_subscriptions.user_id', 'users.id')
                ->whereIn('user_subscriptions.status', ['active', 'trialing', 'past_due'])
                ->where(function ($query) {
                    $query->where('subscription_plans.monthly_price_cents', '>', 0)
                        ->orWhere('subscription_plans.yearly_price_cents', '>', 0);
                }));
    }

    /** @return array<string, mixed> */
    private function metric(string $key, int $value, ?int $denominator, int $minimumGroupSize): array
    {
        $suppressed = ($value > 0 && $value < $minimumGroupSize)
            || ($denominator !== null && $denominator > 0 && $denominator < $minimumGroupSize);

        return [
            'key' => $key,
            'value' => $suppressed ? null : $value,
            'denominator' => $suppressed ? null : $denominator,
            'rate_percent' => $suppressed || ! $denominator
                ? null
                : round(($value / $denominator) * 100, 1),
            'suppressed' => $suppressed,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyDashboard(string $reason, int $days, $since, int $minimumGroupSize): array
    {
        $keys = [
            'consented_cohort',
            'activation',
            'active_users',
            'seven_day_retention',
            'training_engagement',
            'team_engagement',
            'paid_customers',
            'recent_churn',
        ];

        return [
            'status' => $reason,
            'period' => $this->period($days, $since),
            'privacy' => $this->privacyContract($minimumGroupSize, true, $reason),
            'metrics' => array_map(fn (string $key): array => [
                'key' => $key,
                'value' => null,
                'denominator' => null,
                'rate_percent' => null,
                'suppressed' => true,
            ], $keys),
            'definitions' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function privacyContract(int $minimumGroupSize, bool $suppressed, ?string $reason): array
    {
        return [
            'consent_required' => true,
            'ad_consent_reused' => false,
            'minors_excluded' => true,
            'minimum_group_size' => $minimumGroupSize,
            'suppressed' => $suppressed,
            'suppression_reason' => $reason,
            'raw_event_table' => false,
            'tracking_sdk' => false,
            'cookies_added' => false,
            'user_identifiers_exposed' => false,
            'health_or_location_data_exposed' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function period(int $days, $since): array
    {
        return [
            'days' => $days,
            'from' => $since->toDateString(),
            'to' => now()->toDateString(),
        ];
    }
}
