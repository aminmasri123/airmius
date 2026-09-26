<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubAnnouncementPublisher;
use App\Support\CommunicationRecipientSegment;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubAnnouncementController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeAccess($request, $club);
        $user = $request->user();

        $items = ClubAnnouncement::query()
            ->where('club_id', $club->id)
            ->with([
                'team:id,name,club_id,club_department_id',
                'user:id,name',
                'reads' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount('reads')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->filter(fn (ClubAnnouncement $item) => $this->canManageAnnouncement($item, $user)
                || ($item->workflow_status === 'published' && $item->published_at && $item->published_at->isPast() && $this->visibleTo($request, $item)))
            ->values();

        return response()->json(['data' => $items->map(fn (ClubAnnouncement $item) => $this->payload($item, $user))]);
    }

    public function store(Request $request, Club $club)
    {
        $data = $this->validatedData($request, $club, false);
        $teamId = $this->resolveTeamId($data);
        $mode = $data['publication_mode'] ?? 'now';
        $this->authorizeTarget($request, $club, $teamId, ClubPermissions::ANNOUNCEMENTS_EDIT);
        if ($mode !== 'draft') {
            $this->authorizeTarget($request, $club, $teamId, ClubPermissions::ANNOUNCEMENTS_PUBLISH);
        }

        $item = DB::transaction(fn () => ClubAnnouncement::create([
            'club_id' => $club->id,
            'team_id' => $teamId,
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'audience_type' => $data['audience_type'],
            'content_type' => $data['content_type'] ?? 'message',
            ...$this->workflowData($request, $mode),
            ...$this->recipientSnapshot($club, $data['audience_type'], $teamId),
            'published_at' => match ($mode) {
                'draft', 'review' => null,
                'schedule' => $data['publish_at'],
                default => now(),
            },
        ])->load(['team:id,name,club_id,club_department_id', 'user:id,name']));
        if ($mode === 'now') {
            app(ClubAnnouncementPublisher::class)->publishDue();
        }

        return response()->json([
            'message' => __('platform.organization.announcement_published'),
            'data' => $this->payload($item, $request->user()),
        ], 201);
    }

    public function acknowledge(Request $request, Club $club, ClubAnnouncement $announcement)
    {
        abort_unless((int) $announcement->club_id === (int) $club->id, 404);
        $this->authorizeAccess($request, $club);
        abort_unless($announcement->workflow_status === 'published' && $announcement->published_at && $announcement->published_at->isPast(), 404);
        abort_unless($this->visibleTo($request, $announcement), 403, __('platform.organization.announcement_not_available'));
        $read = $announcement->reads()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return response()->json(['message' => __('platform.organization.announcement_read'), 'data' => [
            'announcement_id' => $announcement->id,
            'read_at' => $read->read_at?->toJSON(),
        ]]);
    }

    public function update(Request $request, Club $club, ClubAnnouncement $announcement)
    {
        abort_unless((int) $announcement->club_id === (int) $club->id, 404);
        $this->authorizeAnnouncement($request, $announcement, ClubPermissions::ANNOUNCEMENTS_EDIT);
        abort_if($announcement->notified_at || $announcement->workflow_status === 'withdrawn' || ($announcement->published_at && $announcement->published_at->isPast()), 409);

        $data = $this->validatedData($request, $club, true);
        $teamId = $this->resolveTeamId($data);
        $this->authorizeTarget($request, $club, $teamId, ClubPermissions::ANNOUNCEMENTS_EDIT);
        if (! in_array($data['publication_mode'], ['draft', 'review'], true)) {
            $this->authorizeTarget($request, $club, $teamId, ClubPermissions::ANNOUNCEMENTS_PUBLISH);
        }

        $announcement->update([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'audience_type' => $data['audience_type'],
            'content_type' => $data['content_type'] ?? $announcement->content_type ?? 'message',
            'team_id' => $teamId,
            ...$this->workflowData($request, $data['publication_mode'], $announcement),
            ...$this->recipientSnapshot($club, $data['audience_type'], $teamId),
            'published_at' => match ($data['publication_mode']) {
                'draft', 'review' => null,
                'schedule' => $data['publish_at'],
                default => now(),
            },
        ]);
        if ($data['publication_mode'] === 'now') {
            app(ClubAnnouncementPublisher::class)->publishDue();
        }

        return response()->json(['data' => $this->payload(
            $announcement->fresh()->load(['team:id,name,club_id,club_department_id', 'user:id,name', 'reads']),
            $request->user(),
        )]);
    }

    public function destroy(Request $request, Club $club, ClubAnnouncement $announcement)
    {
        abort_unless((int) $announcement->club_id === (int) $club->id, 404);
        $this->authorizeAnnouncement($request, $announcement, ClubPermissions::ANNOUNCEMENTS_DELETE);
        abort_if($announcement->notified_at || in_array($announcement->workflow_status, ['published', 'withdrawn'], true) || ($announcement->published_at && $announcement->published_at->isPast()), 409);
        $announcement->delete();

        return response()->json([
            'message' => __('platform.organization.announcement_deleted'),
            'data' => ['deleted' => true],
        ]);
    }

    public function publish(Request $request, Club $club, ClubAnnouncement $announcement)
    {
        abort_unless((int) $announcement->club_id === (int) $club->id, 404);
        $this->authorizeAnnouncement($request, $announcement, ClubPermissions::ANNOUNCEMENTS_PUBLISH);
        abort_if($announcement->notified_at || $announcement->workflow_status === 'withdrawn' || ($announcement->published_at && $announcement->published_at->isPast()), 409);
        $announcement->update([
            'workflow_status' => 'published',
            'published_at' => now(),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'withdrawn_by' => null,
            'withdrawn_at' => null,
        ]);
        app(ClubAnnouncementPublisher::class)->publishDue();

        return response()->json([
            'message' => __('platform.organization.announcement_published'),
            'data' => $this->payload(
                $announcement->fresh()->load(['team:id,name,club_id,club_department_id', 'user:id,name', 'reads']),
                $request->user(),
            ),
        ]);
    }

    public function withdraw(Request $request, Club $club, ClubAnnouncement $announcement)
    {
        abort_unless((int) $announcement->club_id === (int) $club->id, 404);
        $this->authorizeAnnouncement($request, $announcement, ClubPermissions::ANNOUNCEMENTS_PUBLISH);
        abort_unless($announcement->workflow_status === 'published', 409);

        $announcement->update([
            'workflow_status' => 'withdrawn',
            'withdrawn_by' => $request->user()->id,
            'withdrawn_at' => now(),
        ]);

        return response()->json([
            'message' => __('platform.organization.announcement_deleted'),
            'data' => $this->payload(
                $announcement->fresh()->load(['team:id,name,club_id,club_department_id', 'user:id,name', 'reads']),
                $request->user(),
            ),
        ]);
    }

    private function validatedData(Request $request, Club $club, bool $requireMode): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
            'audience_type' => ['required', Rule::in(['all_members', 'team'])],
            'content_type' => ['sometimes', Rule::in(['message', 'event', 'result', 'press_release', 'report'])],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'publication_mode' => [$requireMode ? 'required' : 'sometimes', Rule::in(['now', 'draft', 'review', 'schedule'])],
            'publish_at' => ['nullable', 'required_if:publication_mode,schedule', 'date', 'after:now'],
        ]);
    }

    private function resolveTeamId(array $data): ?int
    {
        $teamId = $data['audience_type'] === 'team' ? ($data['team_id'] ?? null) : null;
        if ($data['audience_type'] === 'team' && ! $teamId) {
            throw ValidationException::withMessages(['team_id' => __('organization.survey.team_required')]);
        }

        return $teamId ? (int) $teamId : null;
    }

    private function authorizeAccess(Request $request, Club $club): void
    {
        $user = $request->user();
        if ($this->canManageAnyAnnouncement($club, $user)) {
            return;
        }

        $isMember = $club->users()->where('users.id', $user->id)->where(function ($query) {
            $query->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active');
        })->exists();
        abort_unless($isMember, 403, __('platform.organization.announcement_members_only'));
    }

    private function visibleTo(Request $request, ClubAnnouncement $item): bool
    {
        $item->loadMissing('club');

        return $item->club
            && app(CommunicationRecipientSegment::class)
                ->visibleTo($item->club, $item->audience_type, $item->team_id, $request->user());
    }

    private function payload(ClubAnnouncement $item, User $user): array
    {
        $canEdit = $this->allowsForAnnouncement($item, $user, ClubPermissions::ANNOUNCEMENTS_EDIT);
        $canPublish = $this->allowsForAnnouncement($item, $user, ClubPermissions::ANNOUNCEMENTS_PUBLISH);
        $canDelete = $this->allowsForAnnouncement($item, $user, ClubPermissions::ANNOUNCEMENTS_DELETE);

        return [
            'id' => $item->id,
            'club_id' => $item->club_id,
            'team_id' => $item->team_id,
            'team' => $item->team ? ['id' => $item->team->id, 'name' => $item->team->name] : null,
            'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name] : null,
            'title' => $item->title,
            'body' => $item->body,
            'audience_type' => $item->audience_type,
            'content_type' => $item->content_type ?? 'message',
            'workflow_status' => $item->workflow_status ?? (! $item->published_at ? 'draft' : 'published'),
            'recipient_snapshot' => [
                'count' => (int) $item->recipient_snapshot_count,
                'taken_at' => $item->recipient_snapshot_at?->toJSON(),
            ],
            'published_at' => $item->published_at?->toJSON(),
            'publication_status' => $item->workflow_status === 'withdrawn'
                ? 'withdrawn'
                : (! $item->published_at ? ($item->workflow_status ?? 'draft') : ($item->published_at->isFuture() ? 'scheduled' : 'published')),
            'submitted_for_review_at' => $item->submitted_for_review_at?->toJSON(),
            'reviewed_at' => $item->reviewed_at?->toJSON(),
            'withdrawn_at' => $item->withdrawn_at?->toJSON(),
            'read_by_me' => $item->reads->isNotEmpty(),
            'read_count' => (int) ($item->reads_count ?? $item->reads()->count()),
            'can_manage' => $canEdit || $canPublish || $canDelete,
            'can_edit' => $canEdit,
            'can_publish' => $canPublish,
            'can_delete' => $canDelete,
        ];
    }

    private function workflowData(Request $request, string $mode, ?ClubAnnouncement $announcement = null): array
    {
        return match ($mode) {
            'review' => [
                'workflow_status' => 'in_review',
                'submitted_for_review_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'withdrawn_by' => null,
                'withdrawn_at' => null,
            ],
            'now', 'schedule' => [
                'workflow_status' => 'published',
                'submitted_for_review_at' => $announcement?->submitted_for_review_at,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'withdrawn_by' => null,
                'withdrawn_at' => null,
            ],
            default => [
                'workflow_status' => 'draft',
                'submitted_for_review_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'withdrawn_by' => null,
                'withdrawn_at' => null,
            ],
        };
    }

    private function authorizeAnnouncement(Request $request, ClubAnnouncement $item, string $permission): void
    {
        abort_unless($this->allowsForAnnouncement($item, $request->user(), $permission), 403);
    }

    private function authorizeTarget(Request $request, Club $club, ?int $teamId, string $permission): void
    {
        if ($teamId) {
            $team = Team::query()->with('club')->where('club_id', $club->id)->findOrFail($teamId);
            abort_unless(ClubPermissions::allowsForTeam($team, $request->user(), $permission), 403);

            return;
        }

        abort_unless(ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function allowsForAnnouncement(ClubAnnouncement $item, User $user, string $permission): bool
    {
        if ($item->team_id) {
            $item->loadMissing('team.club');

            return $item->team && ClubPermissions::allowsForTeam($item->team, $user, $permission);
        }

        $item->loadMissing('club');

        return $item->club && ClubPermissions::allows($item->club, $user, $permission);
    }

    private function canManageAnnouncement(ClubAnnouncement $item, User $user): bool
    {
        return collect([
            ClubPermissions::ANNOUNCEMENTS_EDIT,
            ClubPermissions::ANNOUNCEMENTS_PUBLISH,
            ClubPermissions::ANNOUNCEMENTS_DELETE,
        ])->contains(fn (string $permission) => $this->allowsForAnnouncement($item, $user, $permission));
    }

    private function canManageAnyAnnouncement(Club $club, User $user): bool
    {
        return collect([
            ClubPermissions::ANNOUNCEMENTS_EDIT,
            ClubPermissions::ANNOUNCEMENTS_PUBLISH,
            ClubPermissions::ANNOUNCEMENTS_DELETE,
        ])->contains(fn (string $permission) => ClubPermissions::allowsAnyScope($club, $user, $permission));
    }

    private function recipientSnapshot(Club $club, string $audienceType, ?int $teamId): array
    {
        $snapshot = app(CommunicationRecipientSegment::class)->snapshot($club, $audienceType, $teamId);

        return [
            'recipient_snapshot_count' => $snapshot['recipient_count'],
            'recipient_snapshot_hash' => $snapshot['recipient_hash'],
            'recipient_snapshot_at' => now(),
        ];
    }
}
