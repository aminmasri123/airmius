<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FolderPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $user->can('file.view') || $this->isClubAdmin($user) || $this->isCoach($user);
    }

    public function create(User $user)
    {
        return $user->can('file.upload') || $this->isClubAdmin($user) || $this->isCoach($user);
    }

    public function view(User $user, Folder $folder)
    {
        return $folder->user_id === $user->id
            || ($user->can('file.view') && $this->canAccessScope($user, $folder));
    }

    public function delete(User $user, Folder $folder)
    {
        return $folder->user_id === $user->id
            || ($user->can('file.delete') && $this->canAccessScope($user, $folder))
            || $this->isClubAdmin($user);
    }

    public function update(User $user, Folder $folder)
    {
        return $folder->user_id === $user->id
            || ($user->can('file.upload') && $this->canAccessScope($user, $folder))
            || $this->isClubAdmin($user)
            || $this->isCoach($user);
    }

    private function canAccessScope(User $user, Folder $folder): bool
    {
        if ($folder->event) {
            $team = $folder->event->team;
            $club = $folder->event->resolvedClub();

            return $folder->event->participants()->where('users.id', $user->id)->exists()
                || ($team && $team->users()->where('users.id', $user->id)->exists())
                || ($club && $club->users()->where('users.id', $user->id)->exists());
        }

        if ($folder->team) {
            return $folder->team->users()->where('users.id', $user->id)->exists();
        }

        if ($folder->club) {
            return $this->inClub($user, $folder->club);
        }

        return false;
    }
}
