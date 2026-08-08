<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasSubscriptionEntitlements
{
    public function scopeGrantingAccess(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->whereNull($query->qualifyColumn('access_restricted_at'))
            ->where(function (Builder $statusQuery) use ($at): void {
                $statusQuery
                    ->where($statusQuery->qualifyColumn('status'), 'active')
                    ->orWhere(function (Builder $trialQuery) use ($at): void {
                        $trialQuery
                            ->where($trialQuery->qualifyColumn('status'), 'trialing')
                            ->where(function (Builder $endQuery) use ($at): void {
                                $endQuery
                                    ->whereNull($endQuery->qualifyColumn('trial_ends_at'))
                                    ->orWhere($endQuery->qualifyColumn('trial_ends_at'), '>=', $at);
                            });
                    })
                    ->orWhere(function (Builder $cancellationQuery) use ($at): void {
                        $cancellationQuery
                            ->where($cancellationQuery->qualifyColumn('status'), 'cancels_at_period_end')
                            ->where(function (Builder $endQuery) use ($at): void {
                                $endQuery
                                    ->whereNull($endQuery->qualifyColumn('cancels_at'))
                                    ->orWhere($endQuery->qualifyColumn('cancels_at'), '>=', $at);
                            });
                    })
                    ->orWhere(function (Builder $pastDueQuery) use ($at): void {
                        $pastDueQuery
                            ->where($pastDueQuery->qualifyColumn('status'), 'past_due')
                            ->where(function (Builder $graceQuery) use ($at): void {
                                $graceQuery
                                    ->whereNull($graceQuery->qualifyColumn('grace_period_ends_at'))
                                    ->orWhere($graceQuery->qualifyColumn('grace_period_ends_at'), '>=', $at);
                            });
                    });
            });
    }

    public function grantsAccess(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->access_restricted_at) {
            return false;
        }

        return match ($this->status) {
            'active' => true,
            'trialing' => ! $this->trial_ends_at || $this->trial_ends_at->greaterThanOrEqualTo($at),
            'cancels_at_period_end' => ! $this->cancels_at || $this->cancels_at->greaterThanOrEqualTo($at),
            'past_due' => ! $this->grace_period_ends_at || $this->grace_period_ends_at->greaterThanOrEqualTo($at),
            default => false,
        };
    }
}
