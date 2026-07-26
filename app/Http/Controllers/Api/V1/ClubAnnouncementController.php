<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\Team;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubAnnouncementController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $canManage = $this->authorizeAccess($request, $club);
        $teamIds = $canManage ? [] : $club->teams()
            ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
            ->pluck('teams.id')->all();

        $items = ClubAnnouncement::query()
            ->where('club_id', $club->id)
            ->whereNotNull('published_at')
            ->when(! $canManage, function ($query) use ($teamIds) {
                $query->where(function ($visible) use ($teamIds) {
                    $visible->where('audience_type', 'all_members');
                    if ($teamIds !== []) $visible->orWhereIn('team_id', $teamIds);
                });
            })
            ->with(['team:id,name', 'user:id,name', 'reads' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->withCount('reads')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $items->map(fn (ClubAnnouncement $item) => $this->payload($item, $canManage))]);
    }

    public function store(Request $request, Club $club)
    {
        Gate::forUser($request->user())->authorize('update', $club);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
            'audience_type' => ['required', Rule::in(['all_members', 'team'])],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);
        $teamId = $data['team_id'] ?? null;
        if ($data['audience_type'] === 'team') {
            if (! $teamId || ! Team::query()->whereKey($teamId)->where('club_id', $club->id)->exists()) {
                throw ValidationException::withMessages(['team_id' => 'Bitte ein Team dieses Vereins auswählen.']);
            }
        } else {
            $teamId = null;
        }

        $item = DB::transaction(fn () => ClubAnnouncement::create([
            'club_id' => $club->id,
            'team_id' => $teamId,
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'audience_type' => $data['audience_type'],
            'published_at' => now(),
        ])->load(['team:id,name', 'user:id,name']));

        $recipients = $data['audience_type'] === 'team'
            ? $item->team->users()->pluck('users.id')
            : $club->users()->where(function ($query) {
                $query->whereNull('club_user.membership_status')
                    ->orWhereNotIn('club_user.membership_status', ['former', 'paused', 'pending']);
            })->pluck('users.id');
        foreach ($recipients->unique()->reject(fn ($id) => (int) $id === (int) $request->user()->id) as $recipientId) {
            AppNotification::send((int) $recipientId, 'club.announcement', [
                'title' => $item->title,
                'body' => str($item->body)->limit(140)->toString(),
                'url' => '/clubs/'.$club->id.'/announcements/'.$item->id,
                'club_id' => $club->id,
                'announcement_id' => $item->id,
            ]);
        }

        return response()->json(['message' => 'Ankündigung veröffentlicht.', 'data' => $this->payload($item, true)], 201);
    }

    public function acknowledge(Request $request, Club $club, ClubAnnouncement $announcement)
    {
        abort_unless((int) $announcement->club_id === (int) $club->id, 404);
        $this->authorizeAccess($request, $club);
        abort_unless($this->visibleTo($request, $announcement), 403, 'Diese Ankündigung ist für dich nicht freigegeben.');
        $read = $announcement->reads()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['read_at' => now()],
        );
        return response()->json(['message' => 'Lesebestätigung gespeichert.', 'data' => [
            'announcement_id' => $announcement->id,
            'read_at' => $read->read_at?->toJSON(),
        ]]);
    }

    private function authorizeAccess(Request $request, Club $club): bool
    {
        $user = $request->user();
        $canManage = Gate::forUser($user)->allows('update', $club);
        if ($canManage) return true;
        $isMember = $club->users()->where('users.id', $user->id)->where(function ($query) {
            $query->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active');
        })->exists();
        abort_unless($isMember, 403, 'Nur Vereinsmitglieder können Ankündigungen sehen.');
        return false;
    }

    private function visibleTo(Request $request, ClubAnnouncement $item): bool
    {
        if ($item->audience_type === 'all_members') return true;
        return $item->team_id !== null && $item->team()->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists();
    }

    private function payload(ClubAnnouncement $item, bool $canManage): array
    {
        return [
            'id' => $item->id,
            'club_id' => $item->club_id,
            'team_id' => $item->team_id,
            'team' => $item->team ? ['id' => $item->team->id, 'name' => $item->team->name] : null,
            'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name] : null,
            'title' => $item->title,
            'body' => $item->body,
            'audience_type' => $item->audience_type,
            'published_at' => $item->published_at?->toJSON(),
            'read_by_me' => $item->reads->isNotEmpty(),
            'read_count' => (int) ($item->reads_count ?? $item->reads()->count()),
            'can_manage' => $canManage,
        ];
    }
}
