<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use App\Support\RolloutAcceptanceRegistry;
use Illuminate\Http\Request;

final class RolloutDecisionService
{
    /** @return array{enabled:bool,feature:string,stage:int,reason:string} */
    public function forRequest(string $feature, User $user, Request $request): array
    {
        $configured = config("airmius_rollout.features.{$feature}");
        $stage = is_array($configured) ? (int) ($configured['stage'] ?? -1) : -1;

        if (! is_array($configured) || ! in_array($stage, RolloutAcceptanceRegistry::STAGES, true)) {
            return $this->decision(false, $feature, $stage, 'invalid_configuration');
        }

        if (config('airmius_rollout.global_kill_switch') === true) {
            return $this->decision(false, $feature, $stage, 'global_kill_switch');
        }

        if (($configured['kill_switch'] ?? false) === true) {
            return $this->decision(false, $feature, $stage, 'feature_kill_switch');
        }

        if ($stage === 100) {
            return $this->decision(true, $feature, $stage, 'full_rollout');
        }

        if ($this->hasAuthorizedPilotOverride($user, $request)) {
            return $this->decision(true, $feature, $stage, 'authorized_pilot_club');
        }

        if ($stage === 0) {
            return $this->decision(false, $feature, $stage, 'stage_zero');
        }

        $enabled = $this->bucketFor($feature, (string) $user->getAuthIdentifier()) < $stage;

        return $this->decision($enabled, $feature, $stage, $enabled ? 'assigned' : 'outside_stage');
    }

    public function bucketFor(string $feature, string $actorKey): int
    {
        $salt = (string) config('airmius_rollout.salt');
        if ($salt === '') {
            return 100;
        }

        $digest = hash_hmac('sha256', $feature.'|'.$actorKey, $salt, true);
        $number = unpack('Nvalue', substr($digest, 0, 4));

        return ((int) ($number['value'] ?? 0)) % 100;
    }

    private function hasAuthorizedPilotOverride(User $user, Request $request): bool
    {
        if (config('airmius_rollout.pilot_override') !== true || config('airmius_pilot.enabled') !== true) {
            return false;
        }

        $pilotIds = collect(config('airmius_pilot.club_ids', []))
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique();
        if ($pilotIds->isEmpty()) {
            return false;
        }

        $routeClub = $request->route('club');
        $candidate = $routeClub instanceof Club ? $routeClub->getKey() : $routeClub;
        $candidate ??= $request->header('X-Club-ID');
        $candidate ??= $request->hasSession() ? $request->session()->get('club_id') : null;
        $candidate = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($candidate === false || ! $pilotIds->contains((int) $candidate)) {
            return false;
        }

        return Club::query()->linkedToUser($user)->whereKey((int) $candidate)->exists();
    }

    /** @return array{enabled:bool,feature:string,stage:int,reason:string} */
    private function decision(bool $enabled, string $feature, int $stage, string $reason): array
    {
        return compact('enabled', 'feature', 'stage', 'reason');
    }
}
