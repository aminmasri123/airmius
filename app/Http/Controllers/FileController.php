<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Folder;
use App\Models\Team;
use App\Models\User;
use App\Services\FileService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\UploadStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class FileController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_FILES_PER_PAGE = 24;
    private const MAX_FILES_PER_PAGE = 100;
    private const DEFAULT_FOLDERS_PER_PAGE = 24;
    private const MAX_FOLDERS_PER_PAGE = 100;
    private const PAGE_SIZE_OPTIONS = [12, 24, 36, 48, 72, 100];
    private const MAX_FILE_SIZE_KB = 51200;
    private const FILE_NAME_MAX_LENGTH = 180;
    private const SEARCH_QUERY_MAX_LENGTH = 200;
    private const VALID_FILE_SORT_OPTIONS = ['name-asc', 'name-desc', 'newest', 'oldest', 'size-asc', 'size-desc'];
    private const VALID_FOLDER_SORT_OPTIONS = ['name-asc', 'name-desc', 'newest', 'oldest'];

    private const DISALLOWED_FILE_EXTENSIONS = [
        'asp',
        'aspx',
        'bat',
        'cmd',
        'cpl',
        'com',
        'exe',
        'jar',
        'jsp',
        'jspx',
        'js',
        'jse',
        'msc',
        'msi',
        'php',
        'phtml',
        'pif',
        'pl',
        'ps1',
        'py',
        'scr',
        'sh',
        'vbs',
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
        private FileService $service,
        private PlanFeatureService $planFeatures,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', File::class);

        $scope = $this->scopeData($request);
        $currentFolder = null;
        $search = $this->normalizeSearchQuery((string) $request->input('search', ''));
        $fileSort = $this->sanitizeFileSort((string) $request->input('file_sort', 'name-asc'));
        $folderSort = $this->sanitizeFolderSort((string) $request->input('folder_sort', 'name-asc'));
        $filesPerPage = $this->sanitizePerPage(
            (int) $request->integer('per_page', self::DEFAULT_FILES_PER_PAGE),
            self::DEFAULT_FILES_PER_PAGE,
            self::MAX_FILES_PER_PAGE,
            self::PAGE_SIZE_OPTIONS,
        );
        $filesPage = max(1, (int) $request->integer('files_page', 1));
        $foldersPage = max(1, (int) $request->integer('folders_page', 1));
        $foldersPerPage = $this->sanitizePerPage(
            (int) $request->integer('folders_per_page', self::DEFAULT_FOLDERS_PER_PAGE),
            self::DEFAULT_FOLDERS_PER_PAGE,
            self::MAX_FOLDERS_PER_PAGE,
            self::PAGE_SIZE_OPTIONS,
        );

        if ($request->filled('folder_id')) {
            $currentFolder = Folder::query()
                ->with('parent:id,name,parent_id')
                ->where($scope)
                ->findOrFail($request->integer('folder_id'));
        }

        $fileQuery = File::query()
            ->with(['user:id,name', 'club:id,name', 'team:id,name', 'event:id,title', 'folder:id,name'])
            ->where($scope)
            ->where('folder_id', $currentFolder?->id)
            ->whereDoesntHave('messages');

        if ($search !== '') {
            $this->applyDisplayNameSearch($fileQuery, $search);
        }

        $this->applyFileSort($fileQuery, $fileSort);

        $folderQuery = Folder::query()
            ->with('parent:id,name')
            ->withCount('files')
            ->where($scope)
            ->where('parent_id', $currentFolder?->id);

        if ($search !== '') {
            $this->applyFolderNameSearch($folderQuery, $search);
        }

        $this->applyFolderSort($folderQuery, $folderSort);

        $files = $fileQuery
            ->paginate($filesPerPage, ['*'], 'files_page', $filesPage)
            ->withQueryString();

        $folders = $folderQuery
            ->paginate($foldersPerPage, ['*'], 'folders_page', $foldersPage)
            ->withQueryString();

        return Inertia::render('Auth/Dashboard/Files/Index', [
            'files' => $files,
            'folders' => $folders,
            'currentFolder' => $currentFolder,
            'scope' => [
                'type' => $request->input('scope', 'user'),
                'club_id' => $scope['club_id'] ?? null,
                'team_id' => $scope['team_id'] ?? null,
                'event_id' => $scope['event_id'] ?? null,
                'folder_id' => $currentFolder?->id,
            ],
            'file_sort' => $fileSort,
            'folder_sort' => $folderSort,
            'search' => $search,
            'per_page' => $filesPerPage,
            'folders_per_page' => $foldersPerPage,
            'clubs' => Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'users' => $request->user()
                ->friendships()
                ->with('friend:id,name,email')
                ->get()
                ->map(fn ($friendship) => [
                    'id' => $friendship->friend->id,
                    'name' => $friendship->friend->name,
                    'email' => $friendship->friend->email,
                ])
                ->sortBy('name')
                ->values(),
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
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_SIZE_KB],
        ]);

        $scope = $this->authorizeScope($data);

        if (! empty($scope['club_id'])) {
            $club = Club::findOrFail($scope['club_id']);
            $this->planFeatures->ensureCanStoreFile($club, $request->file('file'));
        }

        if (($scope['user_id'] ?? null) === $request->user()->id && empty($scope['club_id']) && empty($scope['team_id']) && empty($scope['event_id'])) {
            $this->planFeatures->ensureCanStoreUserFile($request->user(), $request->file('file'));
        }

        if (!empty($data['folder_id'])) {
            $folder = Folder::findOrFail($data['folder_id']);
            abort_unless($folder->user_id === ($scope['user_id'] ?? null)
                && $folder->club_id === ($scope['club_id'] ?? null)
                && $folder->team_id === ($scope['team_id'] ?? null)
                && $folder->event_id === ($scope['event_id'] ?? null), 422);
        }

        $uploadFile = $request->file('file');
        $this->assertSafeUpload($uploadFile);

        $this->service->upload(auth()->user(), $uploadFile, array_merge($scope, [
            'folder_id' => $data['folder_id'] ?? null,
        ]));

        return back();
    }

    public function download(File $file)
    {
        $this->authorize('view', $file);

        abort_unless(Storage::disk(UploadStorage::disk())->exists($file->path), 404);

        return Storage::disk(UploadStorage::disk())->download($file->path, $file->display_name, [
            'Content-Disposition' => $this->downloadDisposition($file->display_name, false),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $path = $request->boolean('thumbnail') && $file->thumbnail_path
            ? $file->thumbnail_path
            : $file->path;

        abort_unless($path && Storage::disk(UploadStorage::disk())->exists($path), 404);

        $headers = [
            'Content-Disposition' => $this->downloadDisposition($file->display_name, true),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return Storage::disk(UploadStorage::disk())->response($path, $file->display_name, $headers);
    }

    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        $this->service->delete($file);

        return back()->with('success', 'Datei geloescht.');
    }

    public function update(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:'.self::FILE_NAME_MAX_LENGTH],
        ]);

        $safeName = $this->normalizeSafeDisplayName((string) $data['display_name']);

        $file->update([
            'display_name' => $safeName,
        ]);

        return back()->with('success', 'Datei umbenannt.');
    }

    public function share(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $data = $request->validate([
            'target_type' => ['required', Rule::in(['user', 'email'])],
            'target_id' => ['nullable', 'required_if:target_type,user', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'required_if:target_type,email', 'email', 'max:255'],
        ]);

        if ($data['target_type'] === 'email') {
            return $this->shareWithExternalEmail($request, $file, $data['email']);
        }

        $targetUser = User::findOrFail((int) $data['target_id']);

        abort_unless(
            $request->user()->friendships()->where('friend_id', $targetUser->id)->exists(),
            403,
            'Dateien koennen nur mit Freunden geteilt werden.'
        );

        $sharedFile = File::firstOrCreate(
            [
                'user_id' => $targetUser->id,
                'club_id' => null,
                'team_id' => null,
                'event_id' => null,
                'folder_id' => null,
                'path' => $file->path,
            ],
            [
                'display_name' => $file->display_name,
                'type' => $file->type,
                'size' => $file->size,
            ],
        );

        AppNotification::send($targetUser, 'file.shared', [
            'title' => $request->user()->name.' hat eine Datei mit dir geteilt',
            'body' => $file->display_name,
            'url' => route('auth.files.index'),
            'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'file_id' => $sharedFile->id,
        ]);

        return back()->with('success', 'Datei freigegeben.');
    }

    public function sharedDownload(string $token)
    {
        $share = FileShare::query()
            ->with('file')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_if($share->expires_at && $share->expires_at->isPast(), 410, 'Dieser Freigabe-Link ist abgelaufen.');
        abort_unless($share->file && Storage::disk(UploadStorage::disk())->exists($share->file->path), 404);

        $share->forceFill(['downloaded_at' => now()])->save();

        return Storage::disk(UploadStorage::disk())->download($share->file->path, $share->file->display_name, [
            'Content-Disposition' => $this->downloadDisposition($share->file->display_name, false),
            'Cache-Control' => 'private, no-store, no-cache, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        $query = Club::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()));
        $club = $clubId ? $query->findOrFail($clubId) : $query->orderBy('name')->firstOrFail();

        return ['club_id' => $club->id, 'team_id' => null, 'event_id' => null];
    }

    private function teamScope($teamId = null): array
    {
        $query = Team::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()));
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

    private function sanitizeFileSort(string $sort): string
    {
        return in_array($sort, self::VALID_FILE_SORT_OPTIONS, true) ? $sort : 'name-asc';
    }

    private function sanitizeFolderSort(string $sort): string
    {
        return in_array($sort, self::VALID_FOLDER_SORT_OPTIONS, true) ? $sort : 'name-asc';
    }

    private function sanitizePerPage(int $perPage, int $default, int $max, array $allowedSizes): int
    {
        if ($perPage < 1 || $perPage > $max || ! in_array($perPage, $allowedSizes, true)) {
            return $default;
        }

        return $perPage;
    }

    private function applyFileSort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'name-desc' => $query->orderByRaw('LOWER(display_name) DESC'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'size-asc' => $query->orderBy('size')->orderBy('id'),
            'size-desc' => $query->orderByDesc('size')->orderByDesc('id'),
            default => $query->orderByRaw('LOWER(display_name) ASC')->orderBy('id'),
        };
    }

    private function applyFolderSort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'name-desc' => $query->orderByRaw('LOWER(name) DESC'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByRaw('LOWER(name) ASC')->orderBy('id'),
        };
    }

    private function applyDisplayNameSearch(Builder $query, string $search): void
    {
        $needle = '%' . $this->escapeLike($search) . '%';

        $query->whereRaw("LOWER(display_name) LIKE ? ESCAPE '!'", [Str::lower($needle)]);
    }

    private function applyFolderNameSearch(Builder $query, string $search): void
    {
        $needle = '%' . $this->escapeLike($search) . '%';

        $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [Str::lower($needle)]);
    }

    private function normalizeSearchQuery(string $search): string
    {
        $trimmed = trim((string) preg_replace('/[\\x00-\\x1F\\x7F]/', '', $search));

        return trim((string) Str::limit($trimmed, self::SEARCH_QUERY_MAX_LENGTH, ''));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value,
        );
    }

    private function targetScope(string $targetType, int $targetId): array
    {
        if ($targetType === 'team') {
            $team = Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->findOrFail($targetId);

            return [
                'user_id' => auth()->id(),
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

    private function shareWithExternalEmail(Request $request, File $file, string $email)
    {
        $token = Str::random(64);
        $expiresAt = now()->addDays(14);

        $share = FileShare::create([
            'file_id' => $file->id,
            'shared_by_user_id' => $request->user()->id,
            'email' => strtolower($email),
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        $downloadUrl = route('files.shared-download', ['token' => $token]);
        $senderName = $request->user()->name;
        $fileName = $file->display_name;

        try {
            Mail::raw(
                "Hallo,\n\n{$senderName} hat die Datei \"{$fileName}\" mit dir geteilt.\n\nDownload-Link: {$downloadUrl}\n\nDer Link ist bis {$expiresAt->format('d.m.Y H:i')} gueltig.\n\nViele Gruesse\nAirmius",
                function ($message) use ($email, $senderName, $fileName) {
                    $message->to($email)
                        ->subject("{$senderName} hat eine Datei mit dir geteilt: {$fileName}");
                }
            );
        } catch (Throwable) {
            $share->delete();

            return back()->with('error', 'Die E-Mail konnte nicht gesendet werden. Bitte pruefe die Mail-Konfiguration.');
        }

        return back()->with('success', 'Externe Freigabe per E-Mail gesendet.');
    }

    private function assertSafeUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'Die Datei ist ungueltig oder wurde fehlerhaft uebertragen.',
            ]);
        }

        if ((int) $file->getSize() <= 0) {
            throw ValidationException::withMessages([
                'file' => 'Die Datei ist leer oder wurde falsch uebertragen.',
            ]);
        }

        $originalName = (string) $file->getClientOriginalName();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $dispositionParts = array_filter(
            preg_split('/\./', strtolower((string) $originalName), -1, PREG_SPLIT_NO_EMPTY),
            static fn (string $part): bool => $part !== '',
        );

        if (count($dispositionParts) > 1) {
            foreach ($dispositionParts as $part) {
                if (in_array($part, self::DISALLOWED_FILE_EXTENSIONS, true)) {
                    throw ValidationException::withMessages([
                        'file' => 'Dieser Dateityp ist aus Sicherheitsgruenden nicht erlaubt.',
                    ]);
                }
            }
        }

        if ($extension !== '' && in_array($extension, self::DISALLOWED_FILE_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'Dieser Dateityp ist aus Sicherheitsgruenden nicht erlaubt.',
            ]);
        }

        $dangerousMimeTypes = array_filter([
            strtolower((string) $file->getClientMimeType()),
            strtolower((string) $file->getMimeType()),
        ]);

        if ($this->containsDisallowedMimeType($dangerousMimeTypes)) {
            throw ValidationException::withMessages([
                'file' => 'Dieser Dateityp ist aus Sicherheitsgruenden nicht erlaubt.',
            ]);
        }

        if (! $this->isSafeFileName($originalName)) {
            throw ValidationException::withMessages([
                'file' => 'Der Dateiname enthaelt ungueltige Zeichen.',
            ]);
        }
    }

    private function normalizeSafeDisplayName(string $name): string
    {
        $trimmed = trim((string) $name);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'display_name' => 'Der Dateiname darf nicht leer sein.',
            ]);
        }

        if (! $this->isSafeFileName($trimmed)) {
            throw ValidationException::withMessages([
                'display_name' => 'Der Dateiname enthaelt ungueltige Zeichen.',
            ]);
        }

        return $trimmed;
    }

    private function containsDisallowedMimeType(array $mimes): bool
    {
        foreach ($mimes as $mime) {
            if (in_array($mime, self::DISALLOWED_MIME_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeFileName(string $name): bool
    {
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $name)) {
            return false;
        }

        $trimmed = trim((string) $name);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, '.') || str_contains($trimmed, '..')) {
            return false;
        }

        if (str_starts_with(strtolower((string) $trimmed), 'desktop.ini')) {
            return false;
        }

        return strlen($trimmed) <= 255;
    }

    private function downloadDisposition(string $filename, bool $inline): string
    {
        $safeName = $this->sanitizeFileName($filename);
        $encodedName = rawurlencode($filename);

        return ($inline ? 'inline' : 'attachment').'; filename="'.$safeName.'"; filename*=UTF-8\'\''.$encodedName;
    }

    private function sanitizeFileName(string $filename): string
    {
        $filtered = preg_replace('/[\\x00-\\x1F\\x7F"]/', '', (string) $filename);
        $filtered = preg_replace('/\\\\/', '', $filtered);
        $filtered = trim((string) $filtered);

        if ($filtered === '') {
            return 'datei';
        }

        if (strlen($filtered) > self::FILE_NAME_MAX_LENGTH) {
            $filtered = mb_substr($filtered, 0, self::FILE_NAME_MAX_LENGTH);
        }

        if (preg_match('/[^ -~]/', $filtered)) {
            return 'datei';
        }

        return $filtered;
    }
}
