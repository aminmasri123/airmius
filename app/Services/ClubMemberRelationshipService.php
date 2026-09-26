<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubMemberRelationship;
use App\Models\GuardianChildRelationship;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubMemberRelationshipService
{
    /** @param array<int, string> $purposes @param array<int, string> $contactMethods */
    public function upsert(
        Club $club,
        User $member,
        ?User $relatedUser = null,
        ?string $relatedEmail = null,
        ?string $relatedName = null,
        string $relationshipType = 'contact',
        array $purposes = [],
        array $contactMethods = [],
        string $status = ClubMemberRelationship::STATUS_ACTIVE,
        bool $primary = false,
        ?string $validFrom = null,
        ?string $validUntil = null,
        ?User $actor = null,
        ?string $legacySource = null,
        ?int $legacySourceId = null,
        array $metadata = [],
    ): ClubMemberRelationship {
        return DB::transaction(function () use ($club, $member, $relatedUser, $relatedEmail, $relatedName, $relationshipType, $purposes, $contactMethods, $status, $primary, $validFrom, $validUntil, $actor, $legacySource, $legacySourceId, $metadata) {
            $purposes = $this->normalizePurposes($purposes);
            $contactMethods = $this->normalizeContactMethods($contactMethods);
            $email = $this->normalizedEmail($relatedEmail ?? $relatedUser?->email);

            $this->assertRelationshipAllowed($club, $member, $relatedUser, $email, $relatedName, $purposes, $validFrom, $validUntil);

            $identity = $legacySource && $legacySourceId
                ? ['legacy_source' => $legacySource, 'legacy_source_id' => $legacySourceId]
                : [
                    'club_id' => $club->id,
                    'member_user_id' => $member->id,
                    'related_user_id' => $relatedUser?->id,
                    'related_email' => $email,
                    'related_name' => $relatedUser ? null : $relatedName,
                ];

            $relationship = ClubMemberRelationship::query()->updateOrCreate($identity, [
                'club_id' => $club->id,
                'member_user_id' => $member->id,
                'related_user_id' => $relatedUser?->id,
                'related_email' => $email,
                'related_name' => $relatedUser ? null : $relatedName,
                'relationship_type' => $relationshipType,
                'purposes' => $purposes,
                'contact_methods' => $contactMethods,
                'status' => $status,
                'is_primary' => $primary,
                'valid_from' => $validFrom ?? now()->toDateString(),
                'valid_until' => $validUntil,
                'accepted_at' => $status === ClubMemberRelationship::STATUS_ACTIVE ? now() : null,
                'revoked_at' => $status === ClubMemberRelationship::STATUS_REVOKED ? now() : null,
                'created_by_user_id' => $actor?->id,
                'updated_by_user_id' => $actor?->id,
                'metadata' => $this->redactedMetadata($metadata),
            ]);

            if ($primary && $status === ClubMemberRelationship::STATUS_ACTIVE) {
                $this->clearCompetingPrimaryPurposes($relationship);
            }

            $this->syncContributionPayer($relationship);
            $this->audit($relationship, $actor, 'club.member_relationship.saved');

            return $relationship->refresh();
        });
    }

    public function mirrorGuardianRelationship(GuardianChildRelationship $relationship, ?User $actor = null): ClubMemberRelationship
    {
        return $this->upsert(
            $relationship->club,
            $relationship->child,
            $relationship->guardian,
            $relationship->guardian_email,
            null,
            $relationship->relationship_type ?: 'guardian',
            [ClubMemberRelationship::PURPOSE_GUARDIAN, ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT, ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED],
            [ClubMemberRelationship::CONTACT_EMAIL, $relationship->guardian_user_id ? ClubMemberRelationship::CONTACT_IN_APP : null],
            $this->statusFromGuardian($relationship->status),
            $relationship->is_primary,
            $relationship->valid_from?->toDateString(),
            $relationship->valid_until?->toDateString(),
            $actor,
            'guardian_child_relationships',
            $relationship->id,
            ['mirrored_from' => 'guardian_child_relationships'],
        );
    }

    private function clearCompetingPrimaryPurposes(ClubMemberRelationship $relationship): void
    {
        $purposes = $relationship->purposes ?? [];

        ClubMemberRelationship::query()
            ->where('club_id', $relationship->club_id)
            ->where('member_user_id', $relationship->member_user_id)
            ->where('id', '!=', $relationship->id)
            ->where('status', ClubMemberRelationship::STATUS_ACTIVE)
            ->where('is_primary', true)
            ->get()
            ->each(function (ClubMemberRelationship $candidate) use ($purposes): void {
                if (array_intersect($purposes, $candidate->purposes ?? [])) {
                    $candidate->forceFill(['is_primary' => false])->save();
                }
            });
    }

    private function syncContributionPayer(ClubMemberRelationship $relationship): void
    {
        if (
            ! in_array(ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER, $relationship->purposes ?? [], true)
            || $relationship->status !== ClubMemberRelationship::STATUS_ACTIVE
            || ! $relationship->related_user_id
        ) {
            return;
        }

        DB::table('club_user')
            ->where('club_id', $relationship->club_id)
            ->where('user_id', $relationship->member_user_id)
            ->update(['contribution_payer_user_id' => $relationship->related_user_id]);
    }

    private function assertRelationshipAllowed(Club $club, User $member, ?User $relatedUser, ?string $email, ?string $name, array $purposes, ?string $validFrom, ?string $validUntil): void
    {
        throw_if($purposes === [], ValidationException::withMessages([
            'purposes' => 'At least one relationship purpose is required.',
        ]));

        throw_if($relatedUser && $relatedUser->is($member), ValidationException::withMessages([
            'related_user_id' => 'A member cannot be linked to themselves as a separate relationship contact.',
        ]));

        throw_if(! $relatedUser && ! $email && ! trim((string) $name), ValidationException::withMessages([
            'related_email' => 'A related account, email or external contact name is required.',
        ]));

        $memberBelongsToClub = $member->clubs()->whereKey($club->id)->exists()
            || $member->teams()->where('club_id', $club->id)->exists();

        throw_unless($memberBelongsToClub, ValidationException::withMessages([
            'member_user_id' => 'The member does not belong to this club.',
        ]));

        if ($relatedUser) {
            $relatedBelongsToClub = $relatedUser->clubs()->whereKey($club->id)->exists()
                || $relatedUser->teams()->where('club_id', $club->id)->exists();

            throw_unless($relatedBelongsToClub, ValidationException::withMessages([
                'related_user_id' => 'The related account does not belong to this club.',
            ]));
        }

        throw_if($validFrom && $validUntil && $validUntil < $validFrom, ValidationException::withMessages([
            'valid_until' => 'The validity end date must be on or after the start date.',
        ]));
    }

    /** @param array<int, string|null> $purposes */
    private function normalizePurposes(array $purposes): array
    {
        $allowed = [
            ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER,
            ClubMemberRelationship::PURPOSE_GUARDIAN,
            ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT,
            ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED,
        ];

        return array_values(array_intersect($allowed, array_unique(array_filter($purposes))));
    }

    /** @param array<int, string|null> $contactMethods */
    private function normalizeContactMethods(array $contactMethods): array
    {
        $allowed = [
            ClubMemberRelationship::CONTACT_EMAIL,
            ClubMemberRelationship::CONTACT_PHONE,
            ClubMemberRelationship::CONTACT_IN_APP,
            ClubMemberRelationship::CONTACT_POSTAL,
        ];

        return array_values(array_intersect($allowed, array_unique(array_filter($contactMethods))));
    }

    private function redactedMetadata(array $metadata): array
    {
        return Arr::except($metadata, ['email', 'phone', 'address', 'note', 'notes', 'iban']);
    }

    private function audit(ClubMemberRelationship $relationship, ?User $actor, string $type): void
    {
        ClubAuditLog::record($relationship->club, $actor, $type, $relationship, [
            'member_user_id' => $relationship->member_user_id,
            'related_user_id' => $relationship->related_user_id,
            'relationship_type' => $relationship->relationship_type,
            'purposes' => $relationship->purposes,
            'contact_methods' => $relationship->contact_methods,
            'status' => $relationship->status,
            'is_primary' => $relationship->is_primary,
            'valid_from' => $relationship->valid_from?->toDateString(),
            'valid_until' => $relationship->valid_until?->toDateString(),
            'legacy_source' => $relationship->legacy_source,
        ]);
    }

    private function statusFromGuardian(string $status): string
    {
        return match ($status) {
            GuardianChildRelationship::STATUS_ACCEPTED => ClubMemberRelationship::STATUS_ACTIVE,
            GuardianChildRelationship::STATUS_INVITED => ClubMemberRelationship::STATUS_INVITED,
            GuardianChildRelationship::STATUS_DECLINED => ClubMemberRelationship::STATUS_DECLINED,
            GuardianChildRelationship::STATUS_REVOKED => ClubMemberRelationship::STATUS_REVOKED,
            GuardianChildRelationship::STATUS_AMBIGUOUS => ClubMemberRelationship::STATUS_AMBIGUOUS,
            default => ClubMemberRelationship::STATUS_AMBIGUOUS,
        };
    }

    private function normalizedEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }
}
