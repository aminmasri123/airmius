<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Folder;
use App\Models\User;
use App\Support\ClubPermissions;

class FolderPolicy extends BasePolicy
{
    public function before($user, $ability)
    {
        return null;
    }

    public function viewAny(User $user)
    {
        return $user->can('file.view') || $this->isClubAdmin($user) || $this->isCoach($user);
    }

    public function create(User $user)
    {
        return $user->can('file.upload')
            || $this->isClubAdmin($user)
            || $this->isCoach($user);
    }

    public function view(User $user, Folder $folder)
    {
        if ($this->ownsPersonalFolder($user, $folder)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $folder, ClubPermissions::FILES_VIEW)) {
            return false;
        }

        return ($user->can('file.view') && $this->canAccessScope($user, $folder))
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_VIEW)
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_EDIT)
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_DELETE)
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_SHARE);
    }

    public function delete(User $user, Folder $folder)
    {
        if ($this->ownsPersonalFolder($user, $folder)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $folder, ClubPermissions::FILES_DELETE)) {
            return false;
        }

        return ($user->can('file.delete') && $this->canAccessScope($user, $folder))
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_DELETE);
    }

    public function update(User $user, Folder $folder)
    {
        if ($this->ownsPersonalFolder($user, $folder)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $folder, ClubPermissions::FILES_EDIT)) {
            return false;
        }

        return ($user->can('file.upload') && $this->canAccessScope($user, $folder))
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_EDIT);
    }

    public function share(User $user, Folder $folder): bool
    {
        if ($this->ownsPersonalFolder($user, $folder)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $folder, ClubPermissions::FILES_SHARE)) {
            return false;
        }

        return ($user->can('file.view') && $this->canAccessScope($user, $folder))
            || $this->allowsScopedAction($user, $folder, ClubPermissions::FILES_SHARE);
    }

    private function ownsPersonalFolder(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id
            && ! $folder->club_id
            && ! $folder->team_id
            && ! $folder->event_id;
    }

    private function canAccessScope(User $user, Folder $folder): bool
    {
        if ($folder->event_id) {
            return Event::query()
                ->visibleTo($user)
                ->whereKey($folder->event_id)
                ->exists();
        }

        if ($folder->team) {
            return $folder->team->users()->where('users.id', $user->id)->exists();
        }

        if ($folder->club) {
            return $this->inClub($user, $folder->club);
        }

        return false;
    }

    private function allowsScopedAction(User $user, Folder $folder, string $permission): bool
    {
        return ClubPermissions::allowsForFileScope(
            $user,
            $permission,
            $folder->club_id ? (int) $folder->club_id : null,
            $folder->team_id ? (int) $folder->team_id : null,
            $folder->event_id ? (int) $folder->event_id : null,
        );
    }

    private function explicitlyDeniesScopedAction(User $user, Folder $folder, string $permission): bool
    {
        return ClubPermissions::explicitlyDeniesForFileScope(
            $user,
            $permission,
            $folder->club_id ? (int) $folder->club_id : null,
            $folder->team_id ? (int) $folder->team_id : null,
            $folder->event_id ? (int) $folder->event_id : null,
        );
    }
}
