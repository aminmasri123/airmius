<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubMembershipProspectResource;
use App\Models\Club;
use App\Models\ClubMembershipProspect;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubMembershipProspectController extends Controller
{
    public function index(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_VIEW), 403);

        $prospects = ClubMembershipProspect::query()
            ->where('club_id', $club->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(['team:id,name', 'membershipType:id,name', 'user:id,name,email', 'membershipRequest'])
            ->orderByRaw('trial_at IS NULL')
            ->orderBy('trial_at')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return ClubMembershipProspectResource::collection($prospects);
    }

    public function store(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        $data = $this->validatedData($request, $club);
        $prospect = $club->membershipProspects()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);
        ClubAuditLog::record($club, $request->user(), 'club.membership_prospect.created', $prospect, [
            'status' => $prospect->status,
            'has_trial' => $prospect->trial_at !== null,
        ]);

        return (new ClubMembershipProspectResource($this->loaded($prospect)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Club $club, ClubMembershipProspect $prospect)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        $this->assertClub($club, $prospect);
        abort_if($prospect->status === 'converted', 422, __('organization.club.prospect_converted_locked'));
        $data = $this->validatedData($request, $club);
        $before = $prospect->only(array_keys($data));
        $prospect->update($data);
        $changedFields = collect(array_keys($data))
            ->filter(fn (string $field) => $before[$field] != $prospect->getAttribute($field))
            ->values()
            ->all();
        ClubAuditLog::record($club, $request->user(), 'club.membership_prospect.updated', $prospect, [
            'changed_fields' => $changedFields,
            'status' => $prospect->status,
        ]);

        return new ClubMembershipProspectResource($this->loaded($prospect));
    }

    public function archive(Request $request, Club $club, ClubMembershipProspect $prospect)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        $this->assertClub($club, $prospect);
        abort_if($prospect->status === 'converted', 422, __('organization.club.prospect_converted_locked'));
        $previousStatus = $prospect->status;
        $prospect->update(['status' => 'archived']);
        ClubAuditLog::record($club, $request->user(), 'club.membership_prospect.archived', $prospect, [
            'from_status' => $previousStatus,
            'to_status' => 'archived',
        ]);

        return new ClubMembershipProspectResource($this->loaded($prospect));
    }

    private function validatedData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(array_values(array_diff(ClubMembershipProspect::STATUSES, ['converted'])))],
            'source' => ['nullable', 'string', 'max:100'],
            'trial_at' => ['nullable', 'date'],
            'trial_outcome' => ['nullable', Rule::in(ClubMembershipProspect::TRIAL_OUTCOMES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
        ]);

        $data['name'] = trim($data['name']);
        $data['email'] = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        $data['phone'] = filled($data['phone'] ?? null) ? trim($data['phone']) : null;
        $data['source'] = filled($data['source'] ?? null) ? trim($data['source']) : null;
        $data['notes'] = filled($data['notes'] ?? null) ? trim($data['notes']) : null;

        return $data;
    }

    private function assertClub(Club $club, ClubMembershipProspect $prospect): void
    {
        abort_unless((int) $prospect->club_id === (int) $club->id, 404);
    }

    private function loaded(ClubMembershipProspect $prospect): ClubMembershipProspect
    {
        return $prospect->refresh()->load(['team:id,name', 'membershipType:id,name', 'user:id,name,email', 'membershipRequest']);
    }
}
