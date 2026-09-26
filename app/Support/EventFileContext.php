<?php

namespace App\Support;

use App\Models\Event;
use App\Models\File;
use App\Models\User;

class EventFileContext
{
    private const PREVIEW_LIMIT = 6;

    public function forWeb(Event $event, User $user): array
    {
        return $this->build(
            $event,
            $user,
            'auth.files.preview',
            'auth.files.index',
            'auth.files.download',
        );
    }

    public function forApi(Event $event, User $user): array
    {
        return $this->build(
            $event,
            $user,
            'api.v1.files.preview',
            'api.v1.files.workspace',
        );
    }

    private function build(
        Event $event,
        User $user,
        string $previewRoute,
        string $workspaceRoute,
        ?string $downloadRoute = null,
    ): array {
        $query = $event->files()->whereDoesntHave('messages');
        $count = (clone $query)->count();
        $files = $query
            ->select(['id', 'event_id', 'display_name', 'type', 'size', 'created_at'])
            ->latest('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get()
            ->map(fn (File $file) => [
                'id' => $file->id,
                'display_name' => $file->display_name,
                'type' => $file->type,
                'size' => (int) $file->size,
                'preview_url' => route($previewRoute, $file->id),
                'download_url' => $downloadRoute ? route($downloadRoute, $file->id) : null,
                'created_at' => $file->created_at?->toJSON(),
            ])
            ->values();

        return [
            'count' => $count,
            'preview_limit' => self::PREVIEW_LIMIT,
            'files' => $files,
            'workspace_url' => route($workspaceRoute, [
                'scope' => 'event',
                'event_id' => $event->id,
            ]),
            'can_upload' => ClubPermissions::allowsForFileScope(
                $user,
                ClubPermissions::FILES_EDIT,
                $event->resolvedClub()?->id,
                $event->team_id,
                $event->id,
            ) || ($user->can('upload', File::class)
                && ! ClubPermissions::explicitlyDeniesForFileScope(
                    $user,
                    ClubPermissions::FILES_EDIT,
                    $event->resolvedClub()?->id,
                    $event->team_id,
                    $event->id,
                )),
        ];
    }
}
