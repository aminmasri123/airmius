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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        $this->authorizeFileAccess($request, $file);

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
        $this->authorizeFileAccess($request, $file);

        $this->files->delete($file);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
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

    private function authorizeFileAccess(Request $request, File $file): void
    {
        abort_unless(
            $file->user_id === $request->user()->id
            || ($file->club_id && Club::query()->whereKey($file->club_id)->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists())
            || ($file->team_id && Team::query()->whereKey($file->team_id)->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists())
            || ($file->event_id && Event::query()->whereKey($file->event_id)->whereHas('participants', fn ($query) => $query->where('users.id', $request->user()->id))->exists()),
            403
        );
    }

    private function assertSafeUpload(UploadedFile $file): void
    {
        if (! $file->isValid() || (int) $file->getSize() <= 0) {
            throw ValidationException::withMessages([
                'file' => 'Die Datei ist ungueltig oder wurde fehlerhaft uebertragen.',
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
                throw ValidationException::withMessages(['file' => 'Dieser Dateityp ist aus Sicherheitsgruenden nicht erlaubt.']);
            }
        }

        if ($extension !== '' && in_array($extension, self::DISALLOWED_FILE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Dieser Dateityp ist aus Sicherheitsgruenden nicht erlaubt.']);
        }

        $mimes = array_filter([
            strtolower((string) $file->getClientMimeType()),
            strtolower((string) $file->getMimeType()),
        ]);

        foreach ($mimes as $mime) {
            if (in_array($mime, self::DISALLOWED_MIME_TYPES, true)) {
                throw ValidationException::withMessages(['file' => 'Dieser Dateityp ist aus Sicherheitsgruenden nicht erlaubt.']);
            }
        }

        if (! $this->isSafeFileName($originalName)) {
            throw ValidationException::withMessages(['file' => 'Der Dateiname enthaelt ungueltige Zeichen.']);
        }
    }

    private function normalizeSafeDisplayName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '' || ! $this->isSafeFileName($trimmed)) {
            throw ValidationException::withMessages(['display_name' => 'Der Dateiname enthaelt ungueltige Zeichen.']);
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
}
