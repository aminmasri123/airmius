<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $user->can('file.view')
            || $this->isClubAdmin($user)
            || $this->isCoach($user);
    }

    public function view(User $user, File $file)
    {
        return $file->user_id === $user->id
            || ($user->can('file.view') && $this->canAccessScope($user, $file));
    }

    public function upload(User $user)
    {
        return $user->can('file.upload')
            || $this->isClubAdmin($user)
            || $this->isCoach($user)
            || $this->hasRole($user, ['media_manager']);
    }

    public function delete(User $user, File $file)
    {
        return $file->user_id === $user->id
            || ($user->can('file.delete') && $this->canAccessScope($user, $file))
            || $this->isClubAdmin($user);
    }

    private function canAccessScope(User $user, File $file): bool
    {
        if ($file->event) {
            $team = $file->event->team;
            $club = $file->event->resolvedClub();

            return $file->event->participants()->where('users.id', $user->id)->exists()
                || ($team && $team->users()->where('users.id', $user->id)->exists())
                || ($club && $club->users()->where('users.id', $user->id)->exists());
        }

        if ($file->team) {
            return $file->team->users()->where('users.id', $user->id)->exists();
        }

        if ($file->club) {
            return $this->inClub($user, $file->club);
        }

        return false;
    }
}
