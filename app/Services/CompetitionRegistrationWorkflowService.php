<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionRegistration;
use App\Models\Team;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetitionRegistrationWorkflowService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_NOMINATED = 'nominated';
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @param array<string, mixed> $data */
    public function nominate(Competition $competition, User $actor, array $data): CompetitionRegistration
    {
        $this->authorize($competition->club, $actor, ClubPermissions::EVENTS_EDIT);
        $this->assertDeadlineOpen($competition);
        $this->assertBelongsToCompetitionClub($competition, $data['competition_class_id'] ?? null, CompetitionClass::class, 'competition_class_id');
        $this->assertTeamBelongsToClub($competition, $data['team_id'] ?? null);

        return DB::transaction(function () use ($competition, $actor, $data): CompetitionRegistration {
            $registration = CompetitionRegistration::query()->create([
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'competition_class_id' => $data['competition_class_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'submitted_by' => $actor->id,
                'status' => self::STATUS_NOMINATED,
                'payload' => $data['payload'] ?? [],
            ]);
            $registration->forceFill([
                'nominated_at' => now(),
                'start_fee_cents' => (int) ($data['start_fee_cents'] ?? 0),
            ])->save();

            $this->notifyManagers($registration, 'competition.registration.nominated', 'Wettkampf-Nominierung angelegt');

            return $registration;
        });
    }

    public function markStartFeePaid(CompetitionRegistration $registration, User $actor): CompetitionRegistration
    {
        $registration->loadMissing('club', 'competition');
        $this->authorize($registration->club, $actor, ClubPermissions::FINANCE_EDIT);

        $registration->forceFill(['start_fee_paid_at' => now()])->save();

        return $registration->fresh();
    }

    public function checkEligibility(CompetitionRegistration $registration, User $actor): CompetitionRegistration
    {
        $registration->loadMissing('club', 'competition', 'rosterEntries.user');
        $this->authorize($registration->club, $actor, ClubPermissions::EVENTS_EDIT);

        $missingLicenses = $registration->rosterEntries
            ->filter(fn ($entry): bool => ! $this->hasValidLicense($entry->user, $registration->competition))
            ->values();

        $payload = $registration->payload ?? [];
        $payload['eligibility'] = [
            'missing_license_user_ids' => $missingLicenses->pluck('user_id')->filter()->values()->all(),
            'checked_at' => now()->toISOString(),
        ];

        $registration->forceFill([
            'status' => $missingLicenses->isEmpty() ? self::STATUS_PENDING_CONFIRMATION : self::STATUS_REJECTED,
            'eligibility_checked_at' => now(),
            'license_status' => $missingLicenses->isEmpty() ? 'valid' : 'invalid',
            'payload' => $payload,
        ])->save();

        $this->notifyManagers(
            $registration->fresh(),
            $missingLicenses->isEmpty() ? 'competition.registration.eligible' : 'competition.registration.rejected',
            $missingLicenses->isEmpty() ? 'Wettkampfmeldung spielberechtigt' : 'Wettkampfmeldung abgelehnt'
        );

        return $registration->fresh();
    }

    public function confirm(CompetitionRegistration $registration, User $actor): CompetitionRegistration
    {
        $registration->loadMissing('club', 'competition', 'rosterEntries.user');
        $this->authorize($registration->club, $actor, ClubPermissions::FINANCE_APPROVE);
        $this->assertDeadlineOpen($registration->competition);

        if ($registration->status !== self::STATUS_PENDING_CONFIRMATION) {
            throw ValidationException::withMessages(['status' => 'Die Meldung ist nicht bestaetigungsbereit.']);
        }

        if ((int) $registration->start_fee_cents > 0 && ! $registration->start_fee_paid_at) {
            throw ValidationException::withMessages(['start_fee_paid_at' => 'Das Startgeld ist noch nicht bezahlt.']);
        }

        $registration->forceFill([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'nomination_confirmed_at' => now(),
        ])->save();

        $this->notifyManagers($registration->fresh(), 'competition.registration.confirmed', 'Wettkampfmeldung bestaetigt');

        return $registration->fresh();
    }

    private function authorize(Club $club, User $actor, string $permission): void
    {
        if (! ClubPermissions::allows($club, $actor, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function assertDeadlineOpen(Competition $competition): void
    {
        if ($competition->registration_deadline_at && $competition->registration_deadline_at->isPast()) {
            throw ValidationException::withMessages([
                'registration_deadline_at' => 'Die Meldefrist ist abgelaufen.',
            ]);
        }
    }

    private function assertTeamBelongsToClub(Competition $competition, mixed $teamId): void
    {
        if (! $teamId) {
            return;
        }

        $belongs = Team::query()
            ->whereKey($teamId)
            ->where('club_id', $competition->club_id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages(['team_id' => __('validation.exists', ['attribute' => 'team_id'])]);
        }
    }

    private function assertBelongsToCompetitionClub(Competition $competition, mixed $id, string $model, string $field): void
    {
        if (! $id) {
            return;
        }

        $belongs = $model::query()
            ->whereKey($id)
            ->where('competition_id', $competition->id)
            ->where('club_id', $competition->club_id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([$field => __('validation.exists', ['attribute' => $field])]);
        }
    }

    private function hasValidLicense(?User $user, Competition $competition): bool
    {
        if (! $user || blank($user->athlete_license_number) || ! $user->athlete_license_valid_until) {
            return false;
        }

        $requiredUntil = $competition->starts_on
            ? Carbon::parse($competition->starts_on)->startOfDay()
            : now()->startOfDay();

        return $user->athlete_license_valid_until->startOfDay()->greaterThanOrEqualTo($requiredUntil);
    }

    private function notifyManagers(CompetitionRegistration $registration, string $type, string $title): void
    {
        $registration->loadMissing('club.users', 'competition');

        $registration->club->users
            ->filter(fn (User $user): bool => ClubPermissions::allows($registration->club, $user, ClubPermissions::EVENTS_EDIT))
            ->each(fn (User $user) => AppNotification::send($user, $type, [
                'title' => $title,
                'body' => $registration->competition->name,
                'competition_id' => $registration->competition_id,
                'competition_registration_id' => $registration->id,
                'status' => $registration->status,
                'url' => '/competitions/'.$registration->competition_id,
                'mobile_url' => 'airmius://competitions/'.$registration->competition_id,
            ], [
                'dedupe_key' => $type.':'.$registration->id.':'.$registration->status,
            ]));
    }
}
