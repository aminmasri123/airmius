<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Folder;
use App\Models\Team;
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Folder $folder)
    {
        $this->authorize('delete', $folder);

        foreach ($folder->files as $file) {
            // optional: Storage::delete($file->path);
            $file->delete();
        }

        $folder->delete();

        return back()->with('success', 'Ordner und Dateien gelöscht.');
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
}
