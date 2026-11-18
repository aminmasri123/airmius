<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RideService
{
    public const RESULT_APPROVED = 'approved';

    public const RESULT_ALREADY_JOINED = 'already_joined';

    public const RESULT_ALREADY_REQUESTED = 'already_requested';

    public const RESULT_FULL = 'full';

    public const RESULT_REQUESTED = 'requested';

    public const RESULT_NOT_FOUND = 'not_found';

    public function create(User $user, array $data): Ride
    {
        return DB::transaction(function () use ($user, $data) {
            return Ride::create([
                ...$data,
                'price_version' => isset($data['cost_mode']) ? 1 : 0,
                'club_id' => in_array($data['visibility'], ['club', 'team'], true)
                    ? ($data['club_id'] ?? null)
                    : null,
                'team_id' => $data['visibility'] === 'team' ? ($data['team_id'] ?? null) : null,
                'driver_id' => $user->id,
            ]);
        });
    }

    public function join(Ride $ride, User $user): bool
    {
        DB::transaction(function () use ($ride, $user) {
            Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $now = now();

            DB::table('ride_users')->updateOrInsert(
                [
                    'ride_id' => $ride->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => Ride::MEMBER_STATUS_ACCEPTED,
                    'message' => null,
                    'responded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        });

        return true;
    }

    public function update(Ride $ride, array $data): void
    {
        DB::transaction(function () use ($ride, $data) {
            $locked = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $data['seats'] < $this->acceptedParticipantsCount($ride->id), 422, 'ride_seats_below_participants');
            $changed = array_key_exists('cost_mode', $data) && (
                $locked->cost_mode !== $data['cost_mode']
                || $locked->cost_currency !== $data['cost_currency']
                || $locked->cost_amount_minor !== $data['cost_amount_minor']
            );
            if ($changed) {
                $data['price_version'] = (int) $locked->price_version + 1;
            }
            $locked->update($data);
            if ($changed) {
                DB::table('ride_users')->where('ride_id', $ride->id)->where('user_id', '!=', $locked->driver_id)
                    ->whereIn('status', [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED])
                    ->update(['price_confirmation_required' => true, 'updated_at' => now()]);
            }
            $ride->refresh();
        });
    }

    public function confirmPrice(Ride $ride, User $user, int $version): bool
    {
        return DB::transaction(function () use ($ride, $user, $version) {
            $locked = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();
            $member = DB::table('ride_users')->where('ride_id', $ride->id)->where('user_id', $user->id)->first();
            abort_unless($member && (int) $locked->driver_id !== (int) $user->id
                && in_array($member->status, [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED], true), 403);
            $this->assertPrice($locked, $version, true);
            if ($version !== (int) $locked->price_version) {
                throw ValidationException::withMessages(['price_version' => __('ride_pricing.price_changed')]);
            }
            if (! $member->price_confirmation_required) {
                return false;
            }
            DB::table('ride_users')->where('id', $member->id)->update([
                ...app(RidePricingService::class)->terms($locked), 'updated_at' => now(),
            ]);

            return true;
        });
    }

    private function assertPrice(Ride $ride, ?int $version, bool $acknowledged): void
    {
        if ($ride->cost_mode !== null && (! $acknowledged || $version !== (int) $ride->price_version)) {
            throw ValidationException::withMessages(['price_version' => __('ride_pricing.price_changed')]);
        }
    }

    public function requestToJoin(Ride $ride, User $user, ?string $message = null, ?int $priceVersion = null, bool $acknowledged = false): string
    {
        return DB::transaction(function () use ($ride, $user, $message, $priceVersion, $acknowledged) {
            $lockedRide = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();
            $this->assertPrice($lockedRide, $priceVersion, $acknowledged);

            $membership = DB::table('ride_users')
                ->where('ride_id', $ride->id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership && $membership->status === Ride::MEMBER_STATUS_ACCEPTED) {
                return self::RESULT_ALREADY_JOINED;
            }

            if ($membership && $membership->status === Ride::MEMBER_STATUS_REQUESTED) {
                if ($message !== null && $message !== ($membership->message ?? '')) {
                    DB::table('ride_users')
                        ->where('id', $membership->id)
                        ->update([
                            'message' => $message,
                            'updated_at' => now(),
                        ]);
                }

                return self::RESULT_ALREADY_REQUESTED;
            }

            if ($this->acceptedParticipantsCount($lockedRide->id) >= (int) $lockedRide->seats) {
                return self::RESULT_FULL;
            }

            $now = now();

            DB::table('ride_users')->upsert(
                [[
                    'ride_id' => $ride->id,
                    'user_id' => $user->id,
                    'status' => Ride::MEMBER_STATUS_REQUESTED,
                    'message' => $message,
                    'responded_at' => null,
                    ...app(RidePricingService::class)->terms($lockedRide),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['ride_id', 'user_id'],
                ['status', 'message', 'responded_at', 'updated_at', 'agreed_cost_mode', 'agreed_cost_amount_minor', 'agreed_cost_currency', 'agreed_price_version', 'price_confirmation_required'],
            );

            return self::RESULT_REQUESTED;
        });
    }

    public function approveRequest(Ride $ride, User $user): string
    {
        return DB::transaction(function () use ($ride, $user) {
            $lockedRide = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $membership = DB::table('ride_users')
                ->where('ride_id', $ride->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $membership || $membership->status !== Ride::MEMBER_STATUS_REQUESTED) {
                return self::RESULT_NOT_FOUND;
            }

            if ($membership->price_confirmation_required) {
                throw ValidationException::withMessages(['price_version' => __('ride_pricing.awaiting_confirmation')]);
            }

            if ($this->acceptedParticipantsCount($lockedRide->id) >= (int) $lockedRide->seats) {
                return self::RESULT_FULL;
            }

            DB::table('ride_users')
                ->where('id', $membership->id)
                ->update([
                    'status' => Ride::MEMBER_STATUS_ACCEPTED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            return self::RESULT_APPROVED;
        });
    }

    public function rejectRequest(Ride $ride, User $user): bool
    {
        $updated = DB::transaction(function () use ($ride, $user) {
            Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $membership = DB::table('ride_users')
                ->where('ride_id', $ride->id)
                ->where('user_id', $user->id)
                ->where('status', Ride::MEMBER_STATUS_REQUESTED)
                ->first();

            if (! $membership) {
                return false;
            }

            $updatedRows = DB::table('ride_users')
                ->where('id', $membership->id)
                ->update([
                    'status' => Ride::MEMBER_STATUS_REJECTED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            return $updatedRows > 0;
        });

        return (bool) $updated;
    }

    private function acceptedParticipantsCount(int $rideId): int
    {
        return (int) DB::table('ride_users')
            ->where('ride_id', $rideId)
            ->where('status', Ride::MEMBER_STATUS_ACCEPTED)
            ->count();
    }
}
