<?php

namespace App\Policies;

use App\Models\File;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class FilePolicy extends BasePolicy
{
    public function before($user, $ability)
    {
        return null;
    }

    public function viewAny(User $user)
    {
        // Every authenticated user has a personal file area. Scoped club,
        // team and event files are still restricted by canAccessScope().
        return true;
    }

    public function view(User $user, File $file)
    {
        return $this->ownsPersonalFile($user, $file)
            || $this->canViewViaVisibleChatMessage($user, $file)
            || $this->isVisibleMembershipApplicationDocument($file)
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
        return $this->ownsPersonalFile($user, $file)
            || ($user->can('file.delete') && $this->canAccessScope($user, $file));
    }

    public function update(User $user, File $file)
    {
        return $this->ownsPersonalFile($user, $file)
            || ($user->can('file.upload') && $this->canAccessScope($user, $file));
    }

    private function ownsPersonalFile(User $user, File $file): bool
    {
        return $file->user_id === $user->id
            && ! $file->club_id
            && ! $file->team_id
            && ! $file->event_id;
    }

    private function isVisibleMembershipApplicationDocument(File $file): bool
    {
        if (! $file->club) {
            return false;
        }

        return collect($file->club->membership_application_documents ?: [])
            ->contains(fn (array $document) => (bool) ($document['is_visible'] ?? false)
                && (int) ($document['file_id'] ?? 0) === (int) $file->id);
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
