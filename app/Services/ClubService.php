<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ClubRegistrationReviewRequested;
use App\Notifications\ClubRegistrationSubmitted;
use App\Support\AppNotification;
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
                'sepa_account_holder' => $data['sepa_account_holder'] ?? null,
                'sepa_iban' => $data['sepa_iban'] ?? null,
                'sepa_bic' => $data['sepa_bic'] ?? null,
                'is_listed' => $data['is_listed'] ?? true,
                'teams_are_listed' => $data['teams_are_listed'] ?? true,
                'members_can_post_to_club' => $data['members_can_post_to_club'] ?? true,
                'members_can_post_to_teams' => $data['members_can_post_to_teams'] ?? true,
                'owner_id' => $user->id,
            ]);

            $club->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner', 'roles' => ['owner']],
            ]);

            $this->assignClubOwnerRole($user);

            $this->gamification->grantToClub($user, $club, 'club_profile_completed', $club, [
                'created_by' => $user->id,
            ]);

            $this->sendRegistrationNotifications($club, $user);

            return $club;
        });
    }

    public function delete(Club $club): bool
    {
        $owner = $club->owner;
        $deleted = (bool) $club->delete();

        if ($deleted && $owner) {
            $this->refreshClubOwnerRole($owner);
        }

        return $deleted;
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

            AppNotification::send($user, 'club.registration_submitted', [
                'title' => 'Vereinsbereich aktiviert',
                'body' => 'Dein Vereinsbereich ist sofort aktiv. Airmius prüft den Vereinsantrag und informiert dich über das Ergebnis.',
                'url' => route('auth.clubs.show', $club->id),
                'club_id' => $club->id,
                'club_name' => $club->name,
                'verification_status' => $club->verification_status,
            ]);

            $reviewers = User::permission('system.manage')->get();
            if ($reviewers->isNotEmpty()) {
                Notification::send($reviewers, new ClubRegistrationReviewRequested($club, $user));
                $reviewers->each(fn (User $reviewer) => AppNotification::send($reviewer, 'club.registration_review_requested', [
                    'title' => 'Neuer Vereinsantrag',
                    'body' => $user->name.' hat den Verein „'.$club->name.'“ registriert.',
                    'url' => route('admin.club-verifications.index'),
                    'club_id' => $club->id,
                    'verification_status' => $club->verification_status,
                ]));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
