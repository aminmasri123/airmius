<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubCategoryAssignment;
use App\Models\ClubCustomFieldValue;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberTimelineEntry;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubMemberDuplicates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubExternalMemberMergeService
{
    private const MEMBERSHIP_FIELDS = [
        'membership_status',
        'club_membership_type_id',
        'family_group_key',
        'contribution_payer_user_id',
        'member_number',
        'contribution_amount',
        'contribution_interval',
        'contribution_next_invoice_on',
        'contribution_last_invoice_at',
        'sepa_iban',
        'sepa_bic',
        'sepa_mandate_reference',
        'sepa_mandate_signed_on',
        'sepa_mandate_active',
        'joined_on',
        'membership_ends_on',
        'membership_end_notified_at',
        'membership_ended_at',
        'membership_notes',
    ];

    public function merge(
        Club $club,
        ClubExternalMember $externalMember,
        User $target,
        User $actor,
        string $resolution,
    ): User {
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);
        $registered = $club->users()->where('users.id', $target->id)->firstOrFail();
        $candidate = ClubMemberDuplicates::candidate($club->loadMissing('users'), $externalMember);

        if (! $candidate || ($candidate['ambiguous'] ?? false) || (int) ($candidate['user_id'] ?? 0) !== (int) $target->id) {
            throw ValidationException::withMessages([
                'target_user_id' => __('organization.club.duplicate_target_invalid'),
            ]);
        }
        $matchReasons = $candidate['reasons'];

        $externalNumber = trim((string) $externalMember->member_number);
        if ($externalNumber !== '' && DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('member_number', $externalNumber)
            ->where('user_id', '!=', $target->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'member_number' => __('organization.club.member_number_duplicate'),
            ]);
        }

        DB::transaction(function () use ($club, $externalMember, $registered, $target, $actor, $resolution, $matchReasons): void {
            $current = $registered->pivot;
            $updates = [];

            foreach (self::MEMBERSHIP_FIELDS as $field) {
                $incoming = $externalMember->getAttribute($field);
                $existing = $current?->getAttribute($field);
                $useIncoming = $resolution === 'use_external'
                    || $this->isBlank($existing);

                if (! $useIncoming || $this->sameValue($existing, $incoming)) {
                    continue;
                }

                $updates[$field] = $field === 'contribution_payer_user_id'
                    && (int) $incoming === (int) $target->id
                        ? null
                        : $incoming;
            }

            if ($updates !== []) {
                $club->users()->updateExistingPivot($target->id, $updates);
            }

            $profileUpdates = [];
            foreach ([
                'phone',
                'country',
                'street',
                'house_number',
                'postal_code',
                'city',
                'athlete_license_number',
                'athlete_license_valid_until',
            ] as $field) {
                $incoming = $externalMember->getAttribute($field);
                $existing = $target->getAttribute($field);
                if (($resolution === 'use_external' || $this->isBlank($existing))
                    && ! $this->sameValue($existing, $incoming)) {
                    $profileUpdates[$field] = $incoming;
                }
            }
            if ($profileUpdates !== []) {
                $target->forceFill($profileUpdates)->save();
            }

            $this->moveMetadata($club, $externalMember, $target, $actor, $resolution);
            $this->moveCategories($club, $externalMember, $target, $actor);
            ClubMemberTimelineEntry::query()
                ->where('club_id', $club->id)
                ->where('subject_type', 'external_member')
                ->where('subject_id', $externalMember->id)
                ->update([
                    'subject_type' => 'member',
                    'subject_id' => $target->id,
                    'updated_at' => now(),
                ]);
            $externalId = $externalMember->id;
            $externalMember->delete();

            ClubAuditLog::record($club, $actor, 'club.member.duplicate_merged', $target, [
                'target_user_id' => $target->id,
                'external_member_id' => $externalId,
                'resolution' => $resolution,
                'match_reasons' => $matchReasons,
                'changed_fields' => array_values(array_unique([
                    ...array_keys($updates),
                    ...array_keys($profileUpdates),
                ])),
            ]);

        });

        return $target->fresh();
    }

    private function moveMetadata(
        Club $club,
        ClubExternalMember $externalMember,
        User $target,
        User $actor,
        string $resolution,
    ): void {
        ClubCustomFieldValue::query()
            ->where('club_id', $club->id)
            ->where('subject_type', 'external_member')
            ->where('subject_id', $externalMember->id)
            ->get()
            ->each(function (ClubCustomFieldValue $source) use ($club, $target, $actor, $resolution): void {
                $targetValue = ClubCustomFieldValue::query()->firstOrNew([
                    'club_id' => $club->id,
                    'club_custom_field_definition_id' => $source->club_custom_field_definition_id,
                    'subject_type' => 'member',
                    'subject_id' => $target->id,
                ]);

                if (! $targetValue->exists || $resolution === 'use_external') {
                    $targetValue->fill([
                        'payload' => $source->payload,
                        'updated_by' => $actor->id,
                    ])->save();
                }
            });
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function moveCategories(
        Club $club,
        ClubExternalMember $externalMember,
        User $target,
        User $actor,
    ): void {
        ClubCategoryAssignment::query()
            ->where('club_id', $club->id)
            ->where('subject_type', 'external_member')
            ->where('subject_id', $externalMember->id)
            ->get()
            ->each(function (ClubCategoryAssignment $source) use ($club, $target, $actor): void {
                ClubCategoryAssignment::query()->firstOrCreate([
                    'club_id' => $club->id,
                    'club_category_id' => $source->club_category_id,
                    'subject_type' => 'member',
                    'subject_id' => $target->id,
                ], [
                    'assigned_by' => $actor->id,
                ]);
            });
    }

    private function sameValue(mixed $first, mixed $second): bool
    {
        if ($first instanceof \DateTimeInterface) {
            $first = $first->format('Y-m-d H:i:s');
        }
        if ($second instanceof \DateTimeInterface) {
            $second = $second->format('Y-m-d H:i:s');
        }

        return (string) $first === (string) $second;
    }
}
