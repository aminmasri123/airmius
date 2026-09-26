<?php

namespace App\Services;

use App\Models\Club;
use App\Models\GuardianChildRelationship;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\MinorSafety;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuardianChildRelationshipService
{
    public function __construct(private readonly ClubMemberRelationshipService $memberRelationships) {}

    public function invite(
        Club $club,
        User $child,
        ?User $guardian = null,
        ?string $guardianEmail = null,
        ?User $actor = null,
        string $relationshipType = 'guardian',
        bool $primary = false,
        bool $backfilledFromLegacy = false,
    ): GuardianChildRelationship {
        return DB::transaction(function () use ($club, $child, $guardian, $guardianEmail, $actor, $relationshipType, $primary, $backfilledFromLegacy) {
            $this->assertLinkAllowed($club, $child, $guardian, $guardianEmail);
            $email = $this->normalizedEmail($guardianEmail ?? $guardian?->email);
            $status = $guardian ? GuardianChildRelationship::STATUS_ACCEPTED : GuardianChildRelationship::STATUS_INVITED;

            $relationship = GuardianChildRelationship::query()->updateOrCreate(
                [
                    'club_id' => $club->id,
                    'child_user_id' => $child->id,
                    'guardian_user_id' => $guardian?->id,
                    'guardian_email' => $email,
                ],
                [
                    'relationship_type' => $relationshipType,
                    'is_primary' => $primary,
                    'status' => $status,
                    'valid_from' => now()->toDateString(),
                    'valid_until' => null,
                    'invited_at' => now(),
                    'accepted_at' => $guardian ? now() : null,
                    'declined_at' => null,
                    'revoked_at' => null,
                    'created_by_user_id' => $actor?->id,
                    'updated_by_user_id' => $actor?->id,
                    'backfilled_from_legacy' => $backfilledFromLegacy,
                    'metadata' => ['source' => $backfilledFromLegacy ? 'legacy_backfill' : 'domain_service'],
                ],
            );

            if ($primary) {
                $this->setPrimary($relationship, $actor, false);
            }

            $this->syncLegacyFields($child, $relationship);
            $this->memberRelationships->mirrorGuardianRelationship($relationship, $actor);
            $this->audit($relationship, $actor, 'club.guardian_child.invited');
            $this->notify($relationship, 'guardian.relationship_invited', $child);

            return $relationship->refresh();
        });
    }

    public function accept(GuardianChildRelationship $relationship, ?User $guardian = null, ?User $actor = null): GuardianChildRelationship
    {
        return $this->transition($relationship, GuardianChildRelationship::STATUS_ACCEPTED, $actor, [
            'guardian_user_id' => $guardian?->id ?? $relationship->guardian_user_id,
            'accepted_at' => now(),
            'declined_at' => null,
            'revoked_at' => null,
        ], 'club.guardian_child.accepted');
    }

    public function decline(GuardianChildRelationship $relationship, ?User $actor = null): GuardianChildRelationship
    {
        return $this->transition($relationship, GuardianChildRelationship::STATUS_DECLINED, $actor, [
            'is_primary' => false,
            'declined_at' => now(),
            'revoked_at' => null,
        ], 'club.guardian_child.declined');
    }

    public function revoke(GuardianChildRelationship $relationship, ?User $actor = null): GuardianChildRelationship
    {
        return $this->transition($relationship, GuardianChildRelationship::STATUS_REVOKED, $actor, [
            'is_primary' => false,
            'revoked_at' => now(),
        ], 'club.guardian_child.revoked');
    }

    public function setPrimary(GuardianChildRelationship $relationship, ?User $actor = null, bool $audit = true): GuardianChildRelationship
    {
        return DB::transaction(function () use ($relationship, $actor, $audit) {
            $relationship = GuardianChildRelationship::query()->lockForUpdate()->findOrFail($relationship->id);

            abort_unless($relationship->status === GuardianChildRelationship::STATUS_ACCEPTED, 422, 'guardian_child_primary_requires_accepted');

            GuardianChildRelationship::query()
                ->where('club_id', $relationship->club_id)
                ->where('child_user_id', $relationship->child_user_id)
                ->where('id', '!=', $relationship->id)
                ->update(['is_primary' => false]);

            $relationship->forceFill([
                'is_primary' => true,
                'updated_by_user_id' => $actor?->id,
            ])->save();

            $this->syncLegacyFields($relationship->child, $relationship);

            if ($audit) {
                $this->audit($relationship, $actor, 'club.guardian_child.primary_changed');
                $this->notify($relationship, 'guardian.relationship_primary_changed', $relationship->child);
            }

            return $relationship->refresh();
        });
    }

    public function backfillLegacyForChild(User $child, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($child, $actor) {
            $clubs = $child->clubs()->get();

            $relationships = $clubs->map(function (Club $club) use ($child, $actor) {
                $guardian = $child->guardian;
                $email = $this->normalizedEmail($child->guardian_email ?? $guardian?->email);

                if (! $guardian && ! $email) {
                    return null;
                }

                $isAmbiguous = $guardian && $email && $this->normalizedEmail($guardian->email) !== $email;
                $relationship = $this->invite(
                    $club,
                    $child,
                    $guardian,
                    $email,
                    $actor,
                    primary: $guardian !== null && ! $isAmbiguous,
                    backfilledFromLegacy: true,
                );

                if ($isAmbiguous) {
                    $relationship->forceFill([
                        'status' => GuardianChildRelationship::STATUS_AMBIGUOUS,
                        'is_primary' => false,
                        'metadata' => array_merge($relationship->metadata ?? [], [
                            'legacy_guardian_user_id' => $guardian->id,
                            'legacy_guardian_email' => $email,
                        ]),
                    ])->save();
                    $this->audit($relationship, $actor, 'club.guardian_child.legacy_ambiguous');
                }

                return $relationship->refresh();
            })->filter()->values();

            return new Collection($relationships->all());
        });
    }

    public function rollbackBackfillForChild(User $child): int
    {
        return GuardianChildRelationship::query()
            ->where('child_user_id', $child->id)
            ->where('backfilled_from_legacy', true)
            ->delete();
    }

    private function transition(GuardianChildRelationship $relationship, string $status, ?User $actor, array $fields, string $auditType): GuardianChildRelationship
    {
        return DB::transaction(function () use ($relationship, $status, $actor, $fields, $auditType) {
            $relationship = GuardianChildRelationship::query()->lockForUpdate()->findOrFail($relationship->id);
            $relationship->forceFill(array_merge($fields, [
                'status' => $status,
                'updated_by_user_id' => $actor?->id,
            ]))->save();

            if ($status === GuardianChildRelationship::STATUS_ACCEPTED && $relationship->is_primary) {
                $this->setPrimary($relationship, $actor, false);
            }

            $this->syncLegacyFields($relationship->child, $relationship);
            $this->memberRelationships->mirrorGuardianRelationship($relationship, $actor);
            $this->audit($relationship, $actor, $auditType);
            $this->notify($relationship, str_replace('club.guardian_child', 'guardian.relationship', $auditType), $relationship->child);

            return $relationship->refresh();
        });
    }

    private function assertLinkAllowed(Club $club, User $child, ?User $guardian, ?string $guardianEmail): void
    {
        throw_if($guardian && $guardian->is($child), ValidationException::withMessages([
            'guardian_user_id' => 'A child cannot be linked as their own guardian.',
        ]));

        throw_if(! MinorSafety::isUnderConsentAge($child), ValidationException::withMessages([
            'child_user_id' => 'Guardian relationships are only supported for children requiring consent.',
        ]));

        $childBelongsToClub = $child->clubs()->whereKey($club->id)->exists()
            || $child->teams()->where('club_id', $club->id)->exists();

        throw_unless($childBelongsToClub, ValidationException::withMessages([
            'club_id' => 'The child does not belong to this club.',
        ]));

        if ($guardian) {
            $guardianBelongsToClub = $guardian->clubs()->whereKey($club->id)->exists()
                || $guardian->teams()->where('club_id', $club->id)->exists();

            throw_unless($guardianBelongsToClub, ValidationException::withMessages([
                'guardian_user_id' => 'The guardian does not belong to this club.',
            ]));
        }

        throw_if(! $guardian && ! $this->normalizedEmail($guardianEmail), ValidationException::withMessages([
            'guardian_email' => 'A guardian account or email is required.',
        ]));
    }

    private function syncLegacyFields(User $child, GuardianChildRelationship $relationship): void
    {
        if (! $relationship->is_primary || $relationship->status !== GuardianChildRelationship::STATUS_ACCEPTED) {
            return;
        }

        $child->forceFill([
            'guardian_user_id' => $relationship->guardian_user_id,
            'guardian_email' => $relationship->guardian_email ?? $relationship->guardian?->email,
        ])->save();
    }

    private function audit(GuardianChildRelationship $relationship, ?User $actor, string $type): void
    {
        ClubAuditLog::record($relationship->club, $actor, $type, $relationship, [
            'child_user_id' => $relationship->child_user_id,
            'guardian_user_id' => $relationship->guardian_user_id,
            'guardian_email' => $relationship->guardian_email,
            'status' => $relationship->status,
            'is_primary' => $relationship->is_primary,
        ]);
    }

    private function notify(GuardianChildRelationship $relationship, string $type, User $child): void
    {
        if ($relationship->guardian_user_id) {
            AppNotification::send($relationship->guardian_user_id, $type, [
                'child_user_id' => $child->id,
                'club_id' => $relationship->club_id,
                'status' => $relationship->status,
            ]);
        }
    }

    private function normalizedEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }
}
