<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberTimelineEntry;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubMemberTimelineController extends Controller
{
    public function store(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);

        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['member', 'external_member'])],
            'subject_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(ClubMemberTimelineEntry::MANUAL_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_on' => ['required', 'date'],
        ]);
        $this->ensureSubjectExists($club, $data['subject_type'], (int) $data['subject_id']);

        $entry = ClubMemberTimelineEntry::query()->create([
            ...$data,
            'club_id' => $club->id,
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'created_by' => $request->user()->id,
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.member.timeline.created', $entry, [
            'entry_type' => $entry->type,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'occurred_on' => $entry->occurred_on?->toDateString(),
        ]);

        return response()->json([
            'message' => __('organization.club.timeline_entry_saved'),
            'data' => $entry->load('creator:id,name')->payload(),
        ], 201);
    }

    public function destroy(Request $request, Club $club, ClubMemberTimelineEntry $timelineEntry)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        abort_unless((int) $timelineEntry->club_id === (int) $club->id, 404);
        abort_unless(in_array($timelineEntry->type, ClubMemberTimelineEntry::MANUAL_TYPES, true), 422);

        ClubAuditLog::record($club, $request->user(), 'club.member.timeline.deleted', $timelineEntry, [
            'entry_type' => $timelineEntry->type,
            'subject_type' => $timelineEntry->subject_type,
            'subject_id' => $timelineEntry->subject_id,
            'occurred_on' => $timelineEntry->occurred_on?->toDateString(),
        ]);
        $timelineEntry->delete();

        return response()->noContent();
    }

    private function ensureSubjectExists(Club $club, string $subjectType, int $subjectId): void
    {
        $exists = $subjectType === 'member'
            ? $club->users()->where('users.id', $subjectId)->exists()
            : ClubExternalMember::query()->where('club_id', $club->id)->whereKey($subjectId)->exists();

        abort_unless($exists, 404);
    }
}
