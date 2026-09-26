<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberQualification;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubMemberQualificationController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $canManage = $this->canManage($request, $club);
        $user = $request->user();
        abort_unless($canManage || $this->isClubMember($club, $user), 403);

        $qualifications = ClubMemberQualification::query()
            ->where('club_id', $club->id)
            ->when($request->integer('user_id'), fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($request->integer('external_member_id'), fn (Builder $query, int $externalId) => $query->where('club_external_member_id', $externalId))
            ->when(! $canManage, fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('visibility', 'member')
                ->where('is_sensitive', false))
            ->orderByRaw('valid_until is null')
            ->orderBy('valid_until')
            ->orderBy('title')
            ->get();

        return response()->json(['data' => [
            'qualifications' => $qualifications->map(fn (ClubMemberQualification $qualification) => $this->payload($qualification, $canManage)),
            'types' => ClubMemberQualification::TYPES,
            'proof_statuses' => ClubMemberQualification::PROOF_STATUSES,
            'visibilities' => ClubMemberQualification::VISIBILITIES,
            'can_manage' => $canManage,
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $this->qualificationData($request, $club);

        $qualification = ClubMemberQualification::query()->create([
            ...$data,
            'club_id' => $club->id,
            'created_by' => $request->user()->id,
            ...$this->proofReviewData($data, $request),
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.member_qualification.created', $qualification, [
            'qualification_id' => $qualification->id,
            'subject_type' => $qualification->user_id ? 'user' : 'external_member',
            'subject_id' => $qualification->user_id ?: $qualification->club_external_member_id,
            'proof_status' => $qualification->proof_status,
            'valid_until' => $qualification->valid_until?->toDateString(),
            'remind_on' => $qualification->remind_on?->toDateString(),
        ]);

        return response()->json(['data' => $this->payload($qualification->refresh(), true)], 201);
    }

    public function update(Request $request, Club $club, ClubMemberQualification $qualification)
    {
        $this->authorizeQualification($request, $club, $qualification);
        $data = $this->qualificationData($request, $club);
        $qualification->update([
            ...$data,
            ...$this->proofReviewData($data, $request),
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.member_qualification.updated', $qualification, [
            'qualification_id' => $qualification->id,
            'proof_status' => $qualification->proof_status,
            'valid_until' => $qualification->valid_until?->toDateString(),
            'remind_on' => $qualification->remind_on?->toDateString(),
        ]);

        return response()->json(['data' => $this->payload($qualification->refresh(), true)]);
    }

    public function destroy(Request $request, Club $club, ClubMemberQualification $qualification)
    {
        $this->authorizeQualification($request, $club, $qualification);
        ClubAuditLog::record($club, $request->user(), 'club.member_qualification.deleted', $qualification, [
            'qualification_id' => $qualification->id,
        ]);
        $qualification->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function qualificationData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'required_without:external_member_id', 'integer'],
            'external_member_id' => ['nullable', 'required_without:user_id', 'integer'],
            'type' => ['required', Rule::in(ClubMemberQualification::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'issuer' => ['nullable', 'string', 'max:160'],
            'license_number' => ['nullable', 'string', 'max:120'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'proof_status' => ['required', Rule::in(ClubMemberQualification::PROOF_STATUSES)],
            'remind_on' => ['nullable', 'date'],
            'is_sensitive' => ['boolean'],
            'visibility' => ['required', Rule::in(ClubMemberQualification::VISIBILITIES)],
        ]);

        if (! empty($data['user_id'])) {
            abort_unless($this->clubHasUser($club, (int) $data['user_id']), 422);
        }

        if (! empty($data['external_member_id'])) {
            abort_unless(ClubExternalMember::query()
                ->where('club_id', $club->id)
                ->whereKey($data['external_member_id'])
                ->exists(), 422);
        }

        if (! empty($data['user_id']) && ! empty($data['external_member_id'])) {
            throw ValidationException::withMessages([
                'member' => 'A qualification may reference either a member or an external member, not both.',
            ]);
        }

        return [
            ...$data,
            'user_id' => $data['user_id'] ?? null,
            'club_external_member_id' => $data['external_member_id'] ?? null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
        ];
    }

    private function proofReviewData(array $data, Request $request): array
    {
        if (! in_array($data['proof_status'], ['verified', 'rejected'], true)) {
            return [
                'proof_checked_at' => null,
                'proof_checked_by' => null,
            ];
        }

        return [
            'proof_checked_at' => now(),
            'proof_checked_by' => $request->user()->id,
        ];
    }

    private function payload(ClubMemberQualification $qualification, bool $canManage): array
    {
        $base = [
            'id' => $qualification->id,
            'club_id' => $qualification->club_id,
            'user_id' => $qualification->user_id,
            'external_member_id' => $qualification->club_external_member_id,
            'type' => $qualification->type,
            'title' => $qualification->title,
            'issuer' => $qualification->issuer,
            'valid_from' => $qualification->valid_from?->toDateString(),
            'valid_until' => $qualification->valid_until?->toDateString(),
            'proof_status' => $qualification->proof_status,
            'remind_on' => $qualification->remind_on?->toDateString(),
            'is_expired' => $qualification->valid_until ? $qualification->valid_until->isPast() : false,
            'is_due_for_reminder' => $qualification->remind_on ? $qualification->remind_on->isPast() || $qualification->remind_on->isToday() : false,
        ];

        if (! $canManage) {
            return $base;
        }

        return [
            ...$base,
            'license_number' => $qualification->license_number,
            'is_sensitive' => $qualification->is_sensitive,
            'visibility' => $qualification->visibility,
            'proof_checked_at' => $qualification->proof_checked_at?->toJSON(),
            'proof_checked_by' => $qualification->proof_checked_by,
        ];
    }

    private function authorizeQualification(Request $request, Club $club, ClubMemberQualification $qualification): void
    {
        $this->authorizeManage($request, $club);
        abort_unless((int) $qualification->club_id === (int) $club->id, 404);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless($this->canManage($request, $club), 403);
    }

    private function canManage(Request $request, Club $club): bool
    {
        return $request->user()
            && ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT);
    }

    private function isClubMember(Club $club, ?User $user): bool
    {
        return $user instanceof User && $this->clubHasUser($club, $user->id);
    }

    private function clubHasUser(Club $club, int $userId): bool
    {
        return $club->users()->where('users.id', $userId)->exists();
    }
}
