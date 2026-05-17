<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
                'club_id' => $data['visibility'] === 'club' ? ($data['club_id'] ?? null) : null,
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

    public function requestToJoin(Ride $ride, User $user, ?string $message = null): string
    {
        return DB::transaction(function () use ($ride, $user, $message) {
            Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

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

            if ($this->acceptedParticipantsCount($ride->id) >= (int) $ride->seats) {
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
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['ride_id', 'user_id'],
                ['status', 'message', 'responded_at', 'updated_at'],
            );

            return self::RESULT_REQUESTED;
        });
    }

    public function approveRequest(Ride $ride, User $user): string
    {
        return DB::transaction(function () use ($ride, $user) {
            Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $membership = DB::table('ride_users')
                ->where('ride_id', $ride->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $membership || $membership->status !== Ride::MEMBER_STATUS_REQUESTED) {
                return self::RESULT_NOT_FOUND;
            }

            if ($this->acceptedParticipantsCount($ride->id) >= (int) $ride->seats) {
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
