<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ClubRegistrationReviewRequested;
use App\Notifications\ClubRegistrationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ClubService
{
    public function __construct(private GamificationService $gamification) {}

    public function create(User $user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $club = Club::create([
                'name' => $data['name'],
                'sport_type' => $data['sport_type'] ?? null,
                'is_official' => false,
                'official_club_number' => null,
                'verification_status' => 'pending_verification',
                'requested_official_club_number' => $data['official_club_number'] ?? null,
                'verification_requested_at' => now(),
                'country' => strtoupper($data['country']),
                'street' => $data['street'] ?? null,
                'house_number' => $data['house_number'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'owner_id' => $user->id,
            ]);

            $club->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner'],
            ]);

            $this->assignClubOwnerRole($user);

            $this->gamification->grantToClub($user, $club, 'club_profile_completed', $club, [
                'created_by' => $user->id,
            ]);

            $this->sendRegistrationNotifications($club, $user);

            return $club;
        });
    }

    public function delete($club)
    {
        return $club->delete();
    }

    public function update($club, array $data)
    {
        $hasSubmittedClubNumber = array_key_exists('official_club_number', $data);
        $submittedClubNumber = $hasSubmittedClubNumber
            ? trim((string) ($data['official_club_number'] ?? ''))
            : null;

        if (isset($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        unset($data['is_official'], $data['official_club_number'], $data['verification_status']);

        if ($hasSubmittedClubNumber) {
            $data['requested_official_club_number'] = $submittedClubNumber !== '' ? $submittedClubNumber : null;

            if ($submittedClubNumber !== '' && $submittedClubNumber !== (string) $club->official_club_number) {
                $data['verification_status'] = 'pending_verification';
                $data['verification_requested_at'] = now();
                $data['verification_notes'] = null;
            }
        }

        $club->update($data);

        return $club;
    }

    public function assignClubOwnerRole(User $user): void
    {
        if (Role::query()->where('name', 'club_owner')->exists() && ! $user->hasRole('club_owner')) {
            $user->assignRole('club_owner');
        }
    }

    public function refreshClubOwnerRole(User $user): void
    {
        if (! Role::query()->where('name', 'club_owner')->exists()) {
            return;
        }

        $ownsClub = Club::query()->where('owner_id', $user->id)->exists();

        if ($ownsClub && ! $user->hasRole('club_owner')) {
            $user->assignRole('club_owner');
            return;
        }

        if (! $ownsClub && $user->hasRole('club_owner')) {
            $user->removeRole('club_owner');
        }
    }

    private function sendRegistrationNotifications(Club $club, User $user): void
    {
        try {
            $user->notify(new ClubRegistrationSubmitted($club));

            $reviewers = User::permission('system.manage')->get();
            if ($reviewers->isNotEmpty()) {
                Notification::send($reviewers, new ClubRegistrationReviewRequested($club, $user));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
