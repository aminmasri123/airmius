<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\Team;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Folder::class);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['user', 'team', 'club', 'event'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:scope,team', 'exists:teams,id'],
            'event_id' => ['nullable', 'required_if:scope,event', 'exists:events,id'],
            'parent_id' => ['nullable', 'exists:folders,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $scope = $this->authorizeScope($data);

        if (! empty($data['parent_id'])) {
            $parent = Folder::findOrFail($data['parent_id']);
            abort_unless($parent->user_id === ($scope['user_id'] ?? null)
                && $parent->club_id === ($scope['club_id'] ?? null)
                && $parent->team_id === ($scope['team_id'] ?? null)
                && $parent->event_id === ($scope['event_id'] ?? null), 422);
        }

        Folder::create(array_merge($scope, [
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
        ]));

        return back()->with('success', 'Ordner erstellt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Folder $folder)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Folder $folder)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Folder $folder)
    {
        $this->authorize('update', $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update([
            'name' => trim($data['name']),
        ]);

        return back()->with('success', 'Ordner umbenannt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Folder $folder)
    {
        $this->authorize('delete', $folder);

        $this->deleteTree($folder);

        return back()->with('success', 'Ordner und Dateien gelöscht.');
    }

    public function share(Request $request, Folder $folder)
    {
        $this->authorize('view', $folder);

        $data = $request->validate([
            'target_type' => ['required', Rule::in(['user'])],
            'target_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $targetUser = User::findOrFail((int) $data['target_id']);

        abort_unless(
            $request->user()->friendships()->where('friend_id', $targetUser->id)->exists(),
            403,
            'Ordner koennen nur mit Freunden geteilt werden.'
        );

        $this->copyTree($folder, $this->targetScope($data['target_type'], $targetUser->id));

        AppNotification::send($targetUser, 'folder.shared', [
            'title' => $request->user()->name.' hat einen Ordner mit dir geteilt',
            'body' => $folder->name,
            'url' => route('auth.files.index'),
            'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'folder_id' => $folder->id,
        ]);

        return back()->with('success', 'Ordner freigegeben.');
    }

    private function authorizeScope(array $data): array
    {
        return match ($data['scope'] ?? 'user') {
            'club' => $this->clubScope($data['club_id']),
            'team' => $this->teamScope($data['team_id']),
            'event' => $this->eventScope($data['event_id']),
            default => ['user_id' => auth()->id(), 'club_id' => null, 'team_id' => null, 'event_id' => null],
        };
    }

    private function clubScope($clubId): array
    {
        $club = Club::visibleTo(auth()->user())->findOrFail($clubId);

        return ['user_id' => null, 'club_id' => $club->id, 'team_id' => null, 'event_id' => null];
    }

    private function teamScope($teamId): array
    {
        $team = Team::visibleTo(auth()->user())->findOrFail($teamId);

        return ['user_id' => null, 'club_id' => $team->club_id, 'team_id' => $team->id, 'event_id' => null];
    }

    private function eventScope($eventId): array
    {
        $event = Event::query()
            ->where(function ($query) {
                $query->whereHas('participants', fn ($q) => $q->where('users.id', auth()->id()))
                    ->orWhereHas('team.users', fn ($q) => $q->where('users.id', auth()->id()))
                    ->orWhereHas('club.users', fn ($q) => $q->where('users.id', auth()->id()));
            })
            ->findOrFail($eventId);

        return [
            'user_id' => null,
            'club_id' => $event->resolvedClub()?->id,
            'team_id' => $event->team_id,
            'event_id' => $event->id,
        ];
    }

    private function deleteTree(Folder $folder): void
    {
        $folder->loadMissing(['children', 'files']);

        foreach ($folder->children as $child) {
            $this->deleteTree($child);
        }

        $folder->files()->delete();
        $folder->delete();
    }

    private function copyTree(Folder $source, array $scope, ?Folder $parent = null): Folder
    {
        $target = Folder::firstOrCreate(
            array_merge($scope, [
                'parent_id' => $parent?->id,
                'name' => $source->name,
            ]),
        );

        $source->loadMissing(['files', 'children']);

        foreach ($source->files as $file) {
            File::firstOrCreate(
                array_merge($scope, [
                    'folder_id' => $target->id,
                    'path' => $file->path,
                ]),
                [
                    'display_name' => $file->display_name,
                    'type' => $file->type,
                    'size' => $file->size,
                ],
            );
        }

        foreach ($source->children as $child) {
            $this->copyTree($child, $scope, $target);
        }

        return $target;
    }

    private function targetScope(string $targetType, int $targetId): array
    {
        if ($targetType === 'team') {
            $team = Team::visibleTo(auth()->user())->findOrFail($targetId);

            return [
                'user_id' => null,
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'event_id' => null,
            ];
        }

        if ($targetType === 'club') {
            $club = Club::visibleTo(auth()->user())->findOrFail($targetId);

            return [
                'user_id' => null,
                'club_id' => $club->id,
                'team_id' => null,
                'event_id' => null,
            ];
        }

        return [
            'user_id' => User::findOrFail($targetId)->id,
            'club_id' => null,
            'team_id' => null,
            'event_id' => null,
        ];
    }
}
