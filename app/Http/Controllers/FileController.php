<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\Team;
use App\Models\User;
use App\Services\FileService;
use App\Services\PlanFeatureService;
use App\Support\UploadStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FileController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private FileService $service,
        private PlanFeatureService $planFeatures,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', File::class);

        $scope = $this->scopeData($request);
        $currentFolder = null;

        if ($request->filled('folder_id')) {
            $currentFolder = Folder::query()
                ->with('parent:id,name,parent_id')
                ->where($scope)
                ->findOrFail($request->integer('folder_id'));
        }

        return Inertia::render('Auth/Dashboard/Files/Index', [
            'files' => File::query()
                ->with(['user:id,name', 'club:id,name', 'team:id,name', 'event:id,title', 'folder:id,name'])
                ->where($scope)
                ->where('folder_id', $currentFolder?->id)
                ->whereDoesntHave('messages')
                ->latest('id')
                ->get(),
            'folders' => Folder::query()
                ->with('parent:id,name')
                ->withCount('files')
                ->where($scope)
                ->where('parent_id', $currentFolder?->id)
                ->orderBy('name')
                ->get(),
            'allFolders' => Folder::query()
                ->where($scope)
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']),
            'currentFolder' => $currentFolder,
            'scope' => [
                'type' => $request->input('scope', 'user'),
                'club_id' => $scope['club_id'] ?? null,
                'team_id' => $scope['team_id'] ?? null,
                'event_id' => $scope['event_id'] ?? null,
                'folder_id' => $currentFolder?->id,
            ],
            'clubs' => Club::query()->visibleTo(auth()->user())->select(['id', 'name'])->orderBy('name')->get(),
            'teams' => Team::query()->visibleTo(auth()->user())->select(['id', 'club_id', 'name'])->orderBy('name')->get(),
            'users' => User::query()
                ->where('id', '!=', auth()->id())
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->limit(200)
                ->get(),
            'events' => Event::query()
                ->where(function ($query) {
                    $query->whereHas('participants', fn ($q) => $q->where('users.id', auth()->id()))
                        ->orWhereHas('team.users', fn ($q) => $q->where('users.id', auth()->id()))
                        ->orWhereHas('club.users', fn ($q) => $q->where('users.id', auth()->id()));
                })
                ->select(['id', 'club_id', 'team_id', 'title'])
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('upload', File::class);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['user', 'team', 'club', 'event'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:scope,team', 'exists:teams,id'],
            'event_id' => ['nullable', 'required_if:scope,event', 'exists:events,id'],
            'folder_id' => ['nullable', 'exists:folders,id'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $scope = $this->authorizeScope($data);

        if (! empty($scope['club_id'])) {
            $club = Club::findOrFail($scope['club_id']);
            $this->planFeatures->ensureCanStoreFile($club, $request->file('file'));
        }

        if (!empty($data['folder_id'])) {
            $folder = Folder::findOrFail($data['folder_id']);
            abort_unless($folder->user_id === ($scope['user_id'] ?? null)
                && $folder->club_id === ($scope['club_id'] ?? null)
                && $folder->team_id === ($scope['team_id'] ?? null)
                && $folder->event_id === ($scope['event_id'] ?? null), 422);
        }

        $this->service->upload(auth()->user(), $request->file('file'), array_merge($scope, [
            'folder_id' => $data['folder_id'] ?? null,
        ]));

        return back();
    }

    public function download(File $file)
    {
        $this->authorize('view', $file);

        abort_unless(Storage::disk(UploadStorage::disk())->exists($file->path), 404);

        return Storage::disk(UploadStorage::disk())->download($file->path);
    }

    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        $this->service->delete($file);

        return back()->with('success', 'Datei gelöscht.');
    }

    public function share(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $data = $request->validate([
            'target_type' => ['required', Rule::in(['user', 'team', 'club'])],
            'target_id' => ['required', 'integer'],
        ]);

        File::firstOrCreate(
            array_merge($this->targetScope($data['target_type'], (int) $data['target_id']), [
                'path' => $file->path,
                'folder_id' => null,
            ]),
            [
                'type' => $file->type,
                'size' => $file->size,
            ],
        );

        return back()->with('success', 'Datei freigegeben.');
    }

    private function scopeData(Request $request): array
    {
        return $this->authorizeScope($request->validate([
            'scope' => ['nullable', Rule::in(['user', 'team', 'club', 'event'])],
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'folder_id' => ['nullable', 'exists:folders,id'],
        ]) + ['scope' => 'user']);
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

    private function clubScope($clubId = null): array
    {
        $query = Club::visibleTo(auth()->user());
        $club = $clubId ? $query->findOrFail($clubId) : $query->orderBy('name')->firstOrFail();

        return ['club_id' => $club->id, 'team_id' => null, 'event_id' => null];
    }

    private function teamScope($teamId = null): array
    {
        $query = Team::visibleTo(auth()->user());
        $team = $teamId ? $query->findOrFail($teamId) : $query->orderBy('name')->firstOrFail();

        return ['club_id' => $team->club_id, 'team_id' => $team->id, 'event_id' => null];
    }

    private function eventScope($eventId = null): array
    {
        $query = Event::query()
            ->where(function ($query) {
                $query->whereHas('participants', fn ($q) => $q->where('users.id', auth()->id()))
                    ->orWhereHas('team.users', fn ($q) => $q->where('users.id', auth()->id()))
                    ->orWhereHas('club.users', fn ($q) => $q->where('users.id', auth()->id()));
            });
        $event = $eventId ? $query->findOrFail($eventId) : $query->orderBy('title')->firstOrFail();

        return [
            'club_id' => $event->resolvedClub()?->id,
            'team_id' => $event->team_id,
            'event_id' => $event->id,
        ];
    }

    private function targetScope(string $targetType, int $targetId): array
    {
        if ($targetType === 'team') {
            $team = Team::visibleTo(auth()->user())->findOrFail($targetId);

            return [
                'user_id' => auth()->id(),
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'event_id' => null,
            ];
        }

        if ($targetType === 'club') {
            $club = Club::visibleTo(auth()->user())->findOrFail($targetId);

            return [
                'user_id' => auth()->id(),
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
