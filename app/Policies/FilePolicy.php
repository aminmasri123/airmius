<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\File;
use App\Models\Message;
use App\Models\User;
use App\Support\ClubPermissions;
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
        if ($file->posts()->where('visibility', 'friends')->get()->contains(fn ($post) => $user->can('view', $post))) {
            return true;
        }

        if ($this->ownsPersonalFile($user, $file)
            || $this->canViewViaVisibleChatMessage($user, $file)
            || $this->isVisibleMembershipApplicationDocument($file)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $file, ClubPermissions::FILES_VIEW)) {
            return false;
        }

        return $this->canAccessScope($user, $file)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_VIEW)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_EDIT)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_DELETE)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_EXPORT)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_SHARE);
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
        if ($file->policyDocuments()->exists()) {
            return false;
        }

        if ($this->ownsPersonalFile($user, $file)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $file, ClubPermissions::FILES_DELETE)) {
            return false;
        }

        return ($user->can('file.delete') && $this->canAccessScope($user, $file))
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_DELETE);
    }

    public function update(User $user, File $file)
    {
        if ($this->ownsPersonalFile($user, $file)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $file, ClubPermissions::FILES_EDIT)) {
            return false;
        }

        return ($user->can('file.upload') && $this->canAccessScope($user, $file))
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_EDIT);
    }

    public function download(User $user, File $file): bool
    {
        if ($this->ownsPersonalFile($user, $file)
            || $this->canViewViaVisibleChatMessage($user, $file)
            || $this->isVisibleMembershipApplicationDocument($file)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $file, ClubPermissions::FILES_EXPORT)) {
            return false;
        }

        return $this->canAccessScope($user, $file)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_EXPORT);
    }

    public function share(User $user, File $file): bool
    {
        if ($this->ownsPersonalFile($user, $file)
            || $this->canViewViaVisibleChatMessage($user, $file)
            || $this->isVisibleMembershipApplicationDocument($file)) {
            return true;
        }

        if ($this->explicitlyDeniesScopedAction($user, $file, ClubPermissions::FILES_SHARE)) {
            return false;
        }

        return $this->canAccessScope($user, $file)
            || $this->allowsScopedAction($user, $file, ClubPermissions::FILES_SHARE);
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
        if ($file->event_id) {
            return Event::query()
                ->visibleTo($user)
                ->whereKey($file->event_id)
                ->exists();
        }

        if ($file->team) {
            return $file->team->users()->where('users.id', $user->id)->exists()
                || $this->managesTeam($user, $file->team);
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

    private function allowsScopedAction(User $user, File $file, string $permission): bool
    {
        return ClubPermissions::allowsForFileScope(
            $user,
            $permission,
            $file->club_id ? (int) $file->club_id : null,
            $file->team_id ? (int) $file->team_id : null,
            $file->event_id ? (int) $file->event_id : null,
        );
    }

    private function explicitlyDeniesScopedAction(User $user, File $file, string $permission): bool
    {
        return ClubPermissions::explicitlyDeniesForFileScope(
            $user,
            $permission,
            $file->club_id ? (int) $file->club_id : null,
            $file->team_id ? (int) $file->team_id : null,
            $file->event_id ? (int) $file->event_id : null,
        );
    }

    private function canViewChatMessage(User $user, Message $message): bool
    {
        $conversation = $message->conversation;

        if (! $conversation || ! $conversation->users()->where('users.id', $user->id)->exists()) {
            return false;
        }

        $membership = DB::table('conversation_users')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first(['joined_at', 'cleared_message_id']);

        if ($membership?->cleared_message_id !== null && $message->id <= $membership->cleared_message_id) {
            return false;
        }

        if ($conversation->type !== 'group') {
            return true;
        }

        return ! $membership?->joined_at || $message->created_at->greaterThanOrEqualTo($membership->joined_at);
    }
}
