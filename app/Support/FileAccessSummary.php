<?php

namespace App\Support;

use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Presents the effective file policy in a user-facing shape.
 *
 * The file policy remains the single source of truth for the booleans. The
 * audience keys explain the scope of each action without exposing internal
 * permission names to clients.
 */
final class FileAccessSummary
{
    public static function for(File $file, ?User $user): array
    {
        $scope = self::scope($file);
        $readAudience = match ($scope) {
            'club' => 'club_members',
            'team' => 'team_members',
            'event' => 'event_members',
            default => 'owner',
        };
        $editAudience = match ($scope) {
            'club' => 'club_file_managers',
            'team' => 'team_file_managers',
            'event' => 'event_file_managers',
            default => 'owner',
        };
        $deleteAudience = match ($scope) {
            'club' => 'club_file_managers_with_delete',
            'team' => 'team_file_managers_with_delete',
            'event' => 'event_file_managers_with_delete',
            default => 'owner',
        };

        $canRead = $user ? Gate::forUser($user)->allows('view', $file) : false;

        return [
            'scope' => $scope,
            'rights' => [
                'read' => [
                    'audience' => $readAudience,
                    'allowed' => $canRead,
                ],
                'edit' => [
                    'audience' => $editAudience,
                    'allowed' => $user ? Gate::forUser($user)->allows('update', $file) : false,
                ],
                'share' => [
                    'audience' => 'readers_with_friendship',
                    'allowed' => $canRead,
                ],
                'delete' => [
                    'audience' => $deleteAudience,
                    'allowed' => $user ? Gate::forUser($user)->allows('delete', $file) : false,
                ],
            ],
        ];
    }

    private static function scope(File $file): string
    {
        if ($file->event_id) {
            return 'event';
        }

        if ($file->team_id) {
            return 'team';
        }

        if ($file->club_id) {
            return 'club';
        }

        return 'personal';
    }
}
