<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\Team;
use App\Models\User;
use App\Services\FileService;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FolderController extends Controller
{
    use AuthorizesRequests;

    private const MAX_FOLDER_COPY_DEPTH = 200;

    private const MAX_FOLDER_NAME_LENGTH = 120;

    private const RESERVED_FOLDER_NAMES = [
        'con',
        'prn',
        'aux',
        'nul',
        'com1',
        'com2',
        'com3',
        'com4',
        'com5',
        'com6',
        'com7',
        'com8',
        'com9',
        'lpt1',
        'lpt2',
        'lpt3',
        'lpt4',
        'lpt5',
        'lpt6',
        'lpt7',
        'lpt8',
        'lpt9',
    ];

    public function __construct(private FileService $service) {}

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
            'name' => [
                'required',
                'string',
                'max:'.self::MAX_FOLDER_NAME_LENGTH,
                'not_regex:/[\\\\\/]/',
                'not_regex:/[\\x00-\\x1F\\x7F]/',
                'not_regex:/^\\.{1,2}$/',
            ],
        ]);

        $scope = $this->authorizeScope($data);

        if (! empty($data['parent_id'])) {
            $parent = Folder::findOrFail($data['parent_id']);
            abort_unless($parent->user_id === ($scope['user_id'] ?? null)
                && $parent->club_id === ($scope['club_id'] ?? null)
                && $parent->team_id === ($scope['team_id'] ?? null)
                && $parent->event_id === ($scope['event_id'] ?? null), 422);
        }

        $normalizedName = trim((string) $data['name']);

        if ($normalizedName === '') {
            throw ValidationException::withMessages([
                'name' => __('file_manager.folder_name_required'),
            ]);
        }

        $this->assertSafeFolderName($normalizedName);

        Folder::create(array_merge($scope, [
            'name' => $normalizedName,
            'parent_id' => $data['parent_id'] ?? null,
        ]));

        return back()->with('success', __('file_manager.folder_created'));
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
            'name' => [
                'required',
                'string',
                'max:'.self::MAX_FOLDER_NAME_LENGTH,
                'not_regex:/[\\\\\/]/',
                'not_regex:/[\\x00-\\x1F\\x7F]/',
                'not_regex:/^\\.{1,2}$/',
            ],
        ]);

        $normalizedName = trim((string) $data['name']);

        if ($normalizedName === '') {
            throw ValidationException::withMessages([
                'name' => __('file_manager.folder_name_required'),
            ]);
        }

        $this->assertSafeFolderName($normalizedName);

        $folder->update([
            'name' => $normalizedName,
        ]);

        return back()->with('success', __('file_manager.folder_renamed'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Folder $folder)
    {
        $this->authorize('delete', $folder);

        $this->deleteTree($folder);

        return back()->with('success', __('file_manager.folder_deleted'));
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
            __('file_manager.folder_friends_only')
        );

        $this->copyTree($folder, $this->targetScope($data['target_type'], $targetUser->id));

        AppNotification::sendLocalized(
            $targetUser,
            'folder.shared',
            'file_manager.folder_shared_title',
            'file_manager.folder_shared_body',
            ['user' => $request->user()->name, 'folder' => $folder->name],
            [
                'url' => route('auth.files.index'),
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'folder_id' => $folder->id,
            ],
        );

        return back()->with('success', __('file_manager.folder_shared'));
    }

    /**
     * JSON variant used by the mobile client. It reuses the same friendship
     * guard and tree-copy implementation as the web flow.
     */
    public function shareApi(Request $request, Folder $folder)
    {
        $this->authorize('view', $folder);

        $data = $request->validate([
            'target_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $targetUser = User::findOrFail((int) $data['target_id']);

        abort_unless(
            $request->user()->friendships()->where('friend_id', $targetUser->id)->exists(),
            403,
            __('file_manager.folder_friends_only')
        );

        $targetFolder = $this->copyTree(
            $folder,
            $this->targetScope('user', $targetUser->id),
        );

        AppNotification::sendLocalized(
            $targetUser,
            'folder.shared',
            'file_manager.folder_shared_title',
            'file_manager.folder_shared_body',
            ['user' => $request->user()->name, 'folder' => $folder->name],
            [
                'url' => route('auth.files.index'),
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'folder_id' => $targetFolder->id,
            ],
        );

        return response()->json([
            'data' => [
                'shared' => true,
                'folder_id' => $folder->id,
                'target_folder_id' => $targetFolder->id,
                'target_user_id' => $targetUser->id,
            ],
        ], 201);
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
        $club = Club::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
            ->findOrFail($clubId);

        return ['user_id' => null, 'club_id' => $club->id, 'team_id' => null, 'event_id' => null];
    }

    private function teamScope($teamId): array
    {
        $team = Team::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
            ->findOrFail($teamId);

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
        $folderIds = $this->collectFolderIds([$folder->id]);

        if (empty($folderIds)) {
            return;
        }

        $files = File::query()
            ->whereIn('folder_id', $folderIds)
            ->get(['id', 'path', 'thumbnail_path']);

        $this->service->deleteMany($files);
        Folder::query()->whereIn('id', $folderIds)->delete();
    }

    private function collectFolderIds(array $rootFolderIds): array
    {
        $collected = [];
        $queue = array_values(array_filter(array_unique(array_map('intval', $rootFolderIds), SORT_NUMERIC)));

        while (! empty($queue)) {
            $batch = array_slice($queue, 0, 100);
            $queue = array_slice($queue, count($batch));

            $newIds = [];
            foreach ($batch as $id) {
                if (! isset($collected[$id])) {
                    $collected[$id] = true;
                }
            }

            if (empty($batch)) {
                continue;
            }

            $children = Folder::query()
                ->whereIn('parent_id', $batch)
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
                if (! isset($collected[$childId])) {
                    $collected[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_map('intval', array_keys($collected));
    }

    private function copyTree(Folder $source, array $scope, ?Folder $parent = null, array &$activePath = [], int $depth = 0): Folder
    {
        if ($depth >= self::MAX_FOLDER_COPY_DEPTH) {
            throw ValidationException::withMessages([
                'target_type' => 'Der Ordnerpfad ist zu tief verschachtelt.',
            ]);
        }

        if (isset($activePath[$source->id])) {
            throw ValidationException::withMessages([
                'target_type' => 'Der Ordner enthält eine zyklische Struktur.',
            ]);
        }

        $activePath[$source->id] = true;

        try {
            $target = Folder::create(
                array_merge($scope, [
                    'parent_id' => $parent?->id,
                    'name' => $this->makeUniqueCopyName($source->name, $scope, $parent?->id),
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
                $this->copyTree($child, $scope, $target, $activePath, $depth + 1);
            }
        } finally {
            unset($activePath[$source->id]);
        }

        return $target;
    }

    private function makeUniqueCopyName(string $name, array $scope, ?int $parentId): string
    {
        $baseName = trim((string) $name);
        $baseName = $baseName === '' ? 'Ordner' : $baseName;
        $candidate = $baseName;
        $suffix = 1;

        while ($this->folderNameExists($candidate, $scope, $parentId)) {
            $suffixLabel = $suffix === 1 ? 'Kopie' : 'Kopie '.$suffix;
            $candidate = "{$baseName} ({$suffixLabel})";
            $suffix++;
        }

        return $candidate;
    }

    private function folderNameExists(string $name, array $scope, ?int $parentId): bool
    {
        return Folder::query()
            ->where($scope)
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $name))])
            ->exists();
    }

    private function assertSafeFolderName(string $name): void
    {
        $normalized = strtolower(trim((string) $name));

        if (in_array($normalized, self::RESERVED_FOLDER_NAMES, true) || str_starts_with($normalized, 'desktop.ini')) {
            throw ValidationException::withMessages([
                'name' => 'Der Ordnername ist ungültig.',
            ]);
        }
    }

    private function targetScope(string $targetType, int $targetId): array
    {
        if ($targetType === 'team') {
            $team = Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->findOrFail($targetId);

            return [
                'user_id' => null,
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'event_id' => null,
            ];
        }

        if ($targetType === 'club') {
            $club = Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->findOrFail($targetId);

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
