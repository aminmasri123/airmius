<?php

namespace App\Policies;

use App\Models\File;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

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
            || $this->canViewViaVisibleChatMessage($user, $file)
            || $this->canAccessScope($user, $file);
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

    public function update(User $user, File $file)
    {
        return $file->user_id === $user->id
            || ($user->can('file.upload') && $this->canAccessScope($user, $file))
            || $this->isClubAdmin($user)
            || $this->isCoach($user);
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

    private function canViewViaVisibleChatMessage(User $user, File $file): bool
    {
        return $file->messages()
            ->with('conversation:id,type')
            ->get()
            ->contains(fn (Message $message) => $this->canViewChatMessage($user, $message));
    }

    private function canViewChatMessage(User $user, Message $message): bool
    {
        $conversation = $message->conversation;

        if (!$conversation || !$conversation->users()->where('users.id', $user->id)->exists()) {
            return false;
        }

        if ($conversation->type !== 'group') {
            return true;
        }

        $joinedAt = DB::table('conversation_users')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->value('joined_at');

        return !$joinedAt || $message->created_at->greaterThanOrEqualTo($joinedAt);
    }
}
