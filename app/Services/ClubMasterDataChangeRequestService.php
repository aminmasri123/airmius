<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubMasterDataChangeRequest;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\ClubProfilePermissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubMasterDataChangeRequestService
{
    public const STATUSES_OPEN = ['pending', 'conflict'];

    private const SENSITIVE_FIELDS = [
        'official_club_number',
        'registry_authority',
        'registry_number',
        'federation_affiliations',
        'tax_authority',
        'tax_number',
        'vat_id',
        'tax_status',
        'tax_exemption_valid_until',
        'sepa_account_holder',
        'sepa_iban',
        'sepa_bic',
    ];

    public function __construct(private readonly ClubService $clubs) {}

    public function create(Club $club, User $actor, array $data): ClubMasterDataChangeRequest
    {
        $fields = $this->submittedMasterDataFields($data);
        if ($fields === []) {
            throw ValidationException::withMessages([
                'fields' => 'Es wurde kein freigegebenes Kontakt- oder Stammdatenfeld übergeben.',
            ]);
        }

        $this->authorizeFields($club, $actor, $fields);
        $this->ensureNoOpenOverlap($club, $fields);

        $freshClub = $club->fresh();
        $baseValues = collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $freshClub->getAttribute($field)])
            ->all();
        $proposedValues = Arr::only($data, $fields);
        $requiresReview = $this->requiresFourEyes($fields);

        return DB::transaction(function () use ($club, $actor, $fields, $baseValues, $proposedValues, $requiresReview) {
            $request = ClubMasterDataChangeRequest::query()->create([
                'club_id' => $club->id,
                'requested_by' => $actor->id,
                'status' => $requiresReview ? 'pending' : 'applied',
                'fields' => $fields,
                'base_values' => $baseValues,
                'proposed_values' => $proposedValues,
                'reviewed_by' => $requiresReview ? null : $actor->id,
                'reviewed_at' => $requiresReview ? null : now(),
            ]);

            ClubAuditLog::record($club, $actor, 'club.master_data_change.requested', $request, [
                'fields' => $fields,
                'sensitive_fields' => array_values(array_intersect($fields, self::SENSITIVE_FIELDS)),
                'requires_four_eyes' => $requiresReview,
            ]);

            if (! $requiresReview) {
                $this->clubs->update($club, $proposedValues, $actor);
                ClubAuditLog::record($club, $actor, 'club.master_data_change.applied', $request, [
                    'fields' => $fields,
                    'review_required' => false,
                ]);
            }

            return $request->fresh();
        });
    }

    public function approve(ClubMasterDataChangeRequest $request, User $reviewer, ?string $note = null): ClubMasterDataChangeRequest
    {
        $club = $request->club()->firstOrFail();
        $this->authorizeFields($club, $reviewer, $request->fields ?? []);

        abort_if((int) $request->requested_by === (int) $reviewer->id, 422, 'Vier-Augen-Freigabe erfordert eine zweite Person.');
        abort_unless(in_array($request->status, self::STATUSES_OPEN, true), 422);

        $conflicts = $this->detectBaseDrift($club->fresh(), $request);
        if ($conflicts !== []) {
            $request->forceFill([
                'status' => 'conflict',
                'conflicts' => $conflicts,
            ])->save();

            ClubAuditLog::record($club, $reviewer, 'club.master_data_change.conflict_detected', $request, [
                'fields' => array_keys($conflicts),
            ]);

            throw ValidationException::withMessages([
                'request' => 'Der Antrag kollidiert mit zwischenzeitlich geänderten Stammdaten.',
            ]);
        }

        return DB::transaction(function () use ($request, $club, $reviewer, $note) {
            $this->clubs->update($club, Arr::only($request->proposed_values ?? [], $request->fields ?? []), $reviewer);
            $request->forceFill([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'conflicts' => null,
            ])->save();

            ClubAuditLog::record($club, $reviewer, 'club.master_data_change.approved', $request, [
                'fields' => $request->fields ?? [],
            ]);

            return $request->fresh();
        });
    }

    public function reject(ClubMasterDataChangeRequest $request, User $reviewer, ?string $note = null): ClubMasterDataChangeRequest
    {
        $club = $request->club()->firstOrFail();
        $this->authorizeFields($club, $reviewer, $request->fields ?? []);

        abort_if((int) $request->requested_by === (int) $reviewer->id, 422, 'Vier-Augen-Ablehnung erfordert eine zweite Person.');
        abort_unless(in_array($request->status, self::STATUSES_OPEN, true), 422);

        $request->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        ClubAuditLog::record($club, $reviewer, 'club.master_data_change.rejected', $request, [
            'fields' => $request->fields ?? [],
        ]);

        return $request->fresh();
    }

    private function submittedMasterDataFields(array $data): array
    {
        $allowed = [
            ...ClubProfilePermissions::LEGAL_FIELDS,
            ...ClubProfilePermissions::CONTACT_FIELDS,
        ];

        return array_values(array_intersect($allowed, array_keys($data)));
    }

    private function authorizeFields(Club $club, User $actor, array $fields): void
    {
        $submitted = array_values(array_unique($fields));
        abort_unless($submitted !== [], 403);

        $requiresLegal = array_intersect($submitted, ClubProfilePermissions::LEGAL_FIELDS) !== [];
        $requiresContact = array_intersect($submitted, ClubProfilePermissions::CONTACT_FIELDS) !== [];

        abort_if($requiresLegal && ! ClubPermissions::allows($club, $actor, ClubPermissions::CLUB_LEGAL_EDIT), 403);
        abort_if($requiresContact && ! ClubPermissions::allows($club, $actor, ClubPermissions::CLUB_CONTACT_EDIT), 403);
    }

    private function ensureNoOpenOverlap(Club $club, array $fields): void
    {
        $overlap = ClubMasterDataChangeRequest::query()
            ->where('club_id', $club->id)
            ->whereIn('status', self::STATUSES_OPEN)
            ->get(['id', 'fields'])
            ->first(fn (ClubMasterDataChangeRequest $request) => array_intersect($fields, $request->fields ?? []) !== []);

        if ($overlap) {
            throw ValidationException::withMessages([
                'fields' => 'Für mindestens eines der Felder liegt bereits ein offener Änderungsantrag vor.',
            ]);
        }
    }

    private function requiresFourEyes(array $fields): bool
    {
        return array_intersect($fields, self::SENSITIVE_FIELDS) !== [];
    }

    private function detectBaseDrift(Club $club, ClubMasterDataChangeRequest $request): array
    {
        $conflicts = [];
        foreach ($request->fields ?? [] as $field) {
            if ($club->getAttribute($field) != ($request->base_values[$field] ?? null)) {
                $conflicts[$field] = 'base_value_changed';
            }
        }

        return $conflicts;
    }
}
