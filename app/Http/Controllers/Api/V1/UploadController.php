<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FileResource;
use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\Team;
use App\Services\FileService;
use App\Services\PlanFeatureService;
use App\Support\Api\V1\ApiPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    private const MAX_FILE_SIZE_KB = 51200;
    private const FILE_NAME_MAX_LENGTH = 180;
    private const DISALLOWED_FILE_EXTENSIONS = [
        'asp', 'aspx', 'bat', 'cmd', 'cpl', 'com', 'exe', 'jar', 'jsp', 'jspx',
        'js', 'jse', 'msc', 'msi', 'php', 'phtml', 'pif', 'pl', 'ps1', 'py',
        'scr', 'sh', 'vbs',
    ];
    private const DISALLOWED_MIME_TYPES = [
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-bat',
        'application/x-msdos',
        'application/vnd.microsoft.portable-executable',
        'application/x-dosexec',
        'application/x-sh',
        'application/x-perl',
        'text/x-perl',
        'application/x-python-code',
        'text/x-python',
        'application/x-php',
        'text/x-php',
        'application/octet-stream',
        'application/x-executable',
    ];

    public function __construct(
        private readonly FileService $files,
        private readonly PlanFeatureService $planFeatures,
    ) {}

    public function index(Request $request)
    {
        $scope = $this->authorizeScope($request, [
            'scope' => $request->input('scope', 'user'),
            'club_id' => $request->input('club_id'),
            'team_id' => $request->input('team_id'),
            'event_id' => $request->input('event_id'),
        ]);

        $files = File::query()
            ->with(['club', 'team', 'event'])
            ->where($scope)
            ->whereDoesntHave('messages')
            ->when($request->filled('folder_id'), fn (Builder $query) => $query->where('folder_id', $request->integer('folder_id')))
            ->latest()
            ->paginate($this->perPage($request));

        return FileResource::collection($files);
    }

    public function workspace(Request $request)
    {
        $scope = $this->authorizeScope($request, [
            'scope' => $request->input('scope', 'user'),
            'club_id' => $request->input('club_id'),
            'team_id' => $request->input('team_id'),
            'event_id' => $request->input('event_id'),
        ]);

        $currentFolder = null;

        if ($request->filled('folder_id')) {
            $currentFolder = Folder::query()
                ->with('parent:id,name,parent_id')
                ->where($scope)
                ->findOrFail($request->integer('folder_id'));
        }

        $search = trim((string) $request->input('search', ''));
        $sort = $this->sort($request);
        $perPage = $this->perPage($request);

        $folders = Folder::query()
            ->withCount('files')
            ->where($scope)
            ->where('parent_id', $currentFolder?->id)
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'));

        $files = File::query()
            ->with(['club', 'team', 'event', 'folder:id,name'])
            ->where($scope)
            ->where('folder_id', $currentFolder?->id)
            ->whereDoesntHave('messages')
            ->when($search !== '', fn (Builder $query) => $query->where('display_name', 'like', '%'.$search.'%'));

        $this->applyFolderSort($folders, $sort);
        $this->applyFileSort($files, $sort);

        $folderPage = $folders->paginate($perPage, ['*'], 'folders_page', max(1, $request->integer('folders_page', 1)));
        $filePage = $files->paginate($perPage, ['*'], 'files_page', max(1, $request->integer('files_page', 1)));

        return response()->json([
            'data' => [
                'scope' => [
                    'type' => $request->input('scope', 'user'),
                    'club_id' => $scope['club_id'] ?? null,
                    'team_id' => $scope['team_id'] ?? null,
                    'event_id' => $scope['event_id'] ?? null,
                    'folder_id' => $currentFolder?->id,
                ],
                'current_folder' => $currentFolder ? $this->folderPayload($currentFolder) : null,
                'folders' => $folderPage->getCollection()->map(fn (Folder $folder) => $this->folderPayload($folder))->values(),
                'files' => FileResource::collection($filePage->getCollection())->resolve(),
                'folders_pagination' => $this->paginationPayload($folderPage),
                'files_pagination' => $this->paginationPayload($filePage),
                'storage_usage' => $this->planFeatures->userStorageSummary($request->user()),
                'search' => $search,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function uploadIntent(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['user', 'team', 'club', 'event'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:scope,team', 'exists:teams,id'],
            'event_id' => ['nullable', 'required_if:scope,event', 'exists:events,id'],
            'folder_id' => ['nullable', 'exists:folders,id'],
            'file_name' => ['required', 'string', 'max:'.self::FILE_NAME_MAX_LENGTH, 'not_regex:#[\\\\/]#', 'not_regex:/[\x00-\x1F\x7F]/', 'not_regex:/^\.{1,2}$/'],
            'mime_type' => ['required', 'string', 'max:160'],
            'size_bytes' => ['nullable', 'integer', 'min:1', 'max:'.(self::MAX_FILE_SIZE_KB * 1024)],
        ]);

        $scope = $this->authorizeScope($request, $data);
        $this->authorizeUploadToScope($request, $scope);
        $this->assertSafeUploadIntent($data['file_name'], $data['mime_type']);

        if (! empty($data['folder_id'])) {
            $folder = Folder::findOrFail($data['folder_id']);
            $this->authorizeFolderAccess($request, $folder);
            $this->assertFolderScope($folder, $scope);
        }

        return response()->json([
            'data' => [
                'id' => 'intent_'.Str::uuid()->toString(),
                'status' => 'ready',
                'upload' => [
                    'method' => 'POST',
                    'endpoint' => '/api/v1/uploads',
                    'field_name' => 'file',
                    'content_type' => 'multipart/form-data',
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'form_fields' => array_filter([
                        'scope' => $data['scope'],
                        'club_id' => $scope['club_id'] ?? null,
                        'team_id' => $scope['team_id'] ?? null,
                        'event_id' => $scope['event_id'] ?? null,
                        'folder_id' => $data['folder_id'] ?? null,
                    ], fn ($value) => $value !== null),
                ],
                'file' => [
                    'file_name' => $this->normalizeSafeDisplayName($data['file_name']),
                    'mime_type' => strtolower($data['mime_type']),
                    'size_bytes' => $data['size_bytes'] ?? null,
                    'max_size_bytes' => self::MAX_FILE_SIZE_KB * 1024,
                    'max_size_kb' => self::MAX_FILE_SIZE_KB,
                ],
                'expires_at' => now()->addMinutes(15)->toJSON(),
            ],
        ], 201);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['user', 'team', 'club', 'event'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:scope,team', 'exists:teams,id'],
            'event_id' => ['nullable', 'required_if:scope,event', 'exists:events,id'],
            'folder_id' => ['nullable', 'exists:folders,id'],
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_SIZE_KB],
        ]);

        $scope = $this->authorizeScope($request, $data);
        $this->authorizeUploadToScope($request, $scope);
        $upload = $request->file('file');
        $this->assertSafeUpload($upload);

        if (! empty($scope['club_id'])) {
            $this->planFeatures->ensureCanStoreFile(Club::findOrFail($scope['club_id']), $upload);
        } elseif (($scope['user_id'] ?? null) === $request->user()->id) {
            $this->planFeatures->ensureCanStoreUserFile($request->user(), $upload);
        }

        if (! empty($data['folder_id'])) {
            $folder = Folder::findOrFail($data['folder_id']);

            abort_unless(
                $folder->user_id === ($scope['user_id'] ?? null)
                && $folder->club_id === ($scope['club_id'] ?? null)
                && $folder->team_id === ($scope['team_id'] ?? null)
                && $folder->event_id === ($scope['event_id'] ?? null),
                422
            );
        }

        $file = $this->files->upload($request->user(), $upload, array_merge($scope, [
            'folder_id' => $data['folder_id'] ?? null,
        ]));

        return (new FileResource($file->loadMissing(['club', 'team', 'event'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, File $file)
    {
        Gate::authorize('update', $file);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:'.self::FILE_NAME_MAX_LENGTH],
        ]);

        $file->update([
            'display_name' => $this->normalizeSafeDisplayName($data['display_name']),
        ]);

        return new FileResource($file->refresh()->loadMissing(['club', 'team', 'event']));
    }

    public function destroy(Request $request, File $file)
    {
        Gate::authorize('delete', $file);

        $this->files->delete($file);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }

    public function storeFolder(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['user', 'team', 'club', 'event'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:scope,team', 'exists:teams,id'],
            'event_id' => ['nullable', 'required_if:scope,event', 'exists:events,id'],
            'parent_id' => ['nullable', 'exists:folders,id'],
            'name' => ['required', 'string', 'max:120', 'not_regex:#[\\\\/]#', 'not_regex:/[\\x00-\\x1F\\x7F]/', 'not_regex:/^\\.{1,2}$/'],
        ]);

        $scope = $this->authorizeScope($request, $data);
        $this->authorizeFolderCreateToScope($request, $scope);
        $name = $this->normalizeFolderName($data['name']);

        if (! empty($data['parent_id'])) {
            $parent = Folder::findOrFail($data['parent_id']);
            $this->authorizeFolderAccess($request, $parent);
            $this->assertFolderScope($parent, $scope);
        }

        $folder = Folder::create(array_merge($scope, [
            'name' => $name,
            'parent_id' => $data['parent_id'] ?? null,
        ]));

        return response()->json(['data' => $this->folderPayload($folder)], 201);
    }

    public function updateFolder(Request $request, Folder $folder)
    {
        Gate::authorize('update', $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:#[\\\\/]#', 'not_regex:/[\\x00-\\x1F\\x7F]/', 'not_regex:/^\\.{1,2}$/'],
        ]);

        $folder->update(['name' => $this->normalizeFolderName($data['name'])]);

        return response()->json(['data' => $this->folderPayload($folder->refresh())]);
    }

    public function destroyFolder(Request $request, Folder $folder)
    {
        Gate::authorize('delete', $folder);
        $this->deleteFolderTree($folder);

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function authorizeScope(Request $request, array $data): array
    {
        return match ($data['scope'] ?? 'user') {
            'club' => $this->clubScope($request, $data['club_id'] ?? null),
            'team' => $this->teamScope($request, $data['team_id'] ?? null),
            'event' => $this->eventScope($request, $data['event_id'] ?? null),
            default => ['user_id' => $request->user()->id, 'club_id' => null, 'team_id' => null, 'event_id' => null],
        };
    }

    private function clubScope(Request $request, $clubId = null): array
    {
        $club = Club::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
            ->findOrFail($clubId);

        return ['club_id' => $club->id, 'team_id' => null, 'event_id' => null];
    }

    private function teamScope(Request $request, $teamId = null): array
    {
        $team = Team::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
            ->findOrFail($teamId);

        return ['club_id' => $team->club_id, 'team_id' => $team->id, 'event_id' => null];
    }

    private function eventScope(Request $request, $eventId = null): array
    {
        $event = Event::query()
            ->where(function ($query) use ($request) {
                $query
                    ->whereHas('participants', fn ($participants) => $participants->where('users.id', $request->user()->id))
                    ->orWhereHas('team.users', fn ($teamUsers) => $teamUsers->where('users.id', $request->user()->id))
                    ->orWhereHas('club.users', fn ($clubUsers) => $clubUsers->where('users.id', $request->user()->id));
            })
            ->findOrFail($eventId);

        return [
            'club_id' => $event->resolvedClub()?->id,
            'team_id' => $event->team_id,
            'event_id' => $event->id,
        ];
    }

    private function authorizeUploadToScope(Request $request, array $scope): void
    {
        if ($this->isPersonalScope($request, $scope)) {
            return;
        }

        Gate::authorize('upload', File::class);
    }

    private function authorizeFolderCreateToScope(Request $request, array $scope): void
    {
        if ($this->isPersonalScope($request, $scope)) {
            return;
        }

        Gate::authorize('create', Folder::class);
    }

    private function isPersonalScope(Request $request, array $scope): bool
    {
        return (int) ($scope['user_id'] ?? 0) === (int) $request->user()->id
            && empty($scope['club_id'])
            && empty($scope['team_id'])
            && empty($scope['event_id']);
    }

    private function authorizeFolderAccess(Request $request, Folder $folder): void
    {
        abort_unless(
            $folder->user_id === $request->user()->id
            || ($folder->club_id && Club::query()->whereKey($folder->club_id)->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists())
            || ($folder->team_id && Team::query()->whereKey($folder->team_id)->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists())
            || ($folder->event_id && Event::query()->whereKey($folder->event_id)->whereHas('participants', fn ($query) => $query->where('users.id', $request->user()->id))->exists()),
            403
        );
    }

    private function assertFolderScope(Folder $folder, array $scope): void
    {
        abort_unless(
            $folder->user_id === ($scope['user_id'] ?? null)
            && $folder->club_id === ($scope['club_id'] ?? null)
            && $folder->team_id === ($scope['team_id'] ?? null)
            && $folder->event_id === ($scope['event_id'] ?? null),
            422
        );
    }

    private function assertSafeUploadIntent(string $fileName, string $mimeType): void
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $dispositionParts = array_filter(
            preg_split('/\./', strtolower($fileName), -1, PREG_SPLIT_NO_EMPTY),
            static fn (string $part): bool => $part !== '',
        );

        foreach ($dispositionParts as $part) {
            if (in_array($part, self::DISALLOWED_FILE_EXTENSIONS, true)) {
                throw ValidationException::withMessages(['file_name' => 'Dieser Dateityp ist aus SicherheitsGruenden nicht erlaubt.']);
            }
        }

        if ($extension !== '' && in_array($extension, self::DISALLOWED_FILE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file_name' => 'Dieser Dateityp ist aus SicherheitsGruenden nicht erlaubt.']);
        }

        if (in_array(strtolower($mimeType), self::DISALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['mime_type' => 'Dieser Dateityp ist aus SicherheitsGruenden nicht erlaubt.']);
        }
    }
    private function assertSafeUpload(UploadedFile $file): void
    {
        if (! $file->isValid() || (int) $file->getSize() <= 0) {
            throw ValidationException::withMessages([
                'file' => 'Die Datei ist ungültig oder wurde fehlerhaft Übertragen.',
            ]);
        }

        $originalName = (string) $file->getClientOriginalName();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $dispositionParts = array_filter(
            preg_split('/\./', strtolower($originalName), -1, PREG_SPLIT_NO_EMPTY),
            static fn (string $part): bool => $part !== '',
        );

        foreach ($dispositionParts as $part) {
            if (in_array($part, self::DISALLOWED_FILE_EXTENSIONS, true)) {
                throw ValidationException::withMessages(['file' => 'Dieser Dateityp ist aus SicherheitsGründen nicht erlaubt.']);
            }
        }

        if ($extension !== '' && in_array($extension, self::DISALLOWED_FILE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Dieser Dateityp ist aus SicherheitsGründen nicht erlaubt.']);
        }

        $mimes = array_filter([
            strtolower((string) $file->getClientMimeType()),
            strtolower((string) $file->getMimeType()),
        ]);

        foreach ($mimes as $mime) {
            if (in_array($mime, self::DISALLOWED_MIME_TYPES, true)) {
                throw ValidationException::withMessages(['file' => 'Dieser Dateityp ist aus SicherheitsGründen nicht erlaubt.']);
            }
        }

        if (! $this->isSafeFileName($originalName)) {
            throw ValidationException::withMessages(['file' => 'Der Dateiname enthält ungültige Zeichen.']);
        }
    }

    private function normalizeSafeDisplayName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '' || ! $this->isSafeFileName($trimmed)) {
            throw ValidationException::withMessages(['display_name' => 'Der Dateiname enthält ungültige Zeichen.']);
        }

        return $trimmed;
    }

    private function normalizeFolderName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'name' => 'Der Ordnername darf nicht leer sein.',
            ]);
        }

        if (in_array(strtolower($trimmed), ['con', 'prn', 'aux', 'nul'], true)) {
            throw ValidationException::withMessages([
                'name' => 'Dieser Ordnername ist reserviert.',
            ]);
        }

        return $trimmed;
    }

    private function isSafeFileName(string $name): bool
    {
        $trimmed = trim($name);

        return $trimmed !== ''
            && strlen($trimmed) <= 255
            && ! preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed)
            && ! str_starts_with($trimmed, '.')
            && ! str_contains($trimmed, '..')
            && ! str_starts_with(strtolower($trimmed), 'desktop.ini');
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 24), 1), 100);
    }

    private function deleteFolderTree(Folder $folder): void
    {
        $folder->loadMissing(['children', 'files']);

        foreach ($folder->children as $child) {
            $this->deleteFolderTree($child);
        }

        foreach ($folder->files as $file) {
            $this->files->delete($file);
        }

        $folder->delete();
    }

    private function sort(Request $request): string
    {
        $sort = (string) $request->input('sort', 'name-asc');

        return in_array($sort, ['name-asc', 'name-desc', 'newest', 'oldest', 'size-asc', 'size-desc'], true)
            ? $sort
            : 'name-asc';
    }

    private function applyFolderSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name-desc' => $query->orderByDesc('name'),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $query->orderBy('name'),
        };
    }

    private function applyFileSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name-desc' => $query->orderByDesc('display_name')->orderByDesc('path'),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            'size-asc' => $query->orderBy('size')->orderBy('display_name'),
            'size-desc' => $query->orderByDesc('size')->orderBy('display_name'),
            default => $query->orderBy('display_name')->orderBy('path'),
        };
    }

    private function folderPayload(Folder $folder): array
    {
        return [
            'id' => $folder->id,
            'user_id' => $folder->user_id,
            'club_id' => $folder->club_id,
            'team_id' => $folder->team_id,
            'event_id' => $folder->event_id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'files_count' => $folder->files_count ?? $folder->files()->count(),
            'created_at' => $folder->created_at?->toJSON(),
            'updated_at' => $folder->updated_at?->toJSON(),
        ];
    }

    private function paginationPayload($page): array
    {
        return array_merge(ApiPagination::meta($page), [
            'links' => ApiPagination::links($page),
        ]);
    }
}
