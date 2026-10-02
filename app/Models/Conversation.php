<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_MEMBER = 'member';

    public const GROUP_ROLES = [self::ROLE_OWNER, self::ROLE_MODERATOR, self::ROLE_MEMBER];

    public const POSTING_ALL = 'all';

    public const POSTING_MANAGEMENT = 'management';

    public const POSTING_POLICIES = [self::POSTING_ALL, self::POSTING_MANAGEMENT];

    protected $fillable = ['club_id', 'team_id', 'owner_id', 'type', 'name', 'description', 'posting_policy'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_users')
            ->withPivot('role', 'joined_at', 'muted_until', 'cleared_at', 'cleared_message_id');
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->whereExists(function ($membership) use ($userId) {
            $membership->selectRaw('1')
                ->from('conversation_users as visible_membership')
                ->whereColumn('visible_membership.conversation_id', 'conversations.id')
                ->where('visible_membership.user_id', $userId)
                ->where(function ($visible) {
                    $visible->whereNull('visible_membership.cleared_message_id')
                        ->orWhereExists(function ($newMessage) {
                            $newMessage->selectRaw('1')
                                ->from('messages as visible_message')
                                ->whereColumn('visible_message.conversation_id', 'conversations.id')
                                ->whereColumn('visible_message.id', '>', 'visible_membership.cleared_message_id')
                                ->whereNull('visible_message.deleted_at')
                                ->where('visible_message.moderation_status', '!=', 'removed');
                        });
                });
        });
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function invitations()
    {
        return $this->hasMany(ConversationInvitation::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function event()
    {
        return $this->hasOne(Event::class);
    }

    public function roleFor(int $userId): ?string
    {
        if ((int) $this->owner_id === $userId) {
            return self::ROLE_OWNER;
        }

        $role = $this->users()
            ->where('users.id', $userId)
            ->value('conversation_users.role');

        return $role ?: null;
    }

    public function isOwner(int $userId): bool
    {
        return $this->type === 'group' && $this->roleFor($userId) === self::ROLE_OWNER;
    }

    public function isModerator(int $userId): bool
    {
        return $this->type === 'group' && $this->roleFor($userId) === self::ROLE_MODERATOR;
    }

    public function canManageMembers(int $userId): bool
    {
        return $this->isOwner($userId) || $this->isModerator($userId);
    }

    public function canSendMessages(int $userId): bool
    {
        if ($this->type !== 'group' || $this->posting_policy !== self::POSTING_MANAGEMENT) {
            return true;
        }

        return $this->canManageMembers($userId);
    }

    public function ownerCount(): int
    {
        $roleOwnerIds = $this->users()
            ->wherePivot('role', self::ROLE_OWNER)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        return $this->owner_id
            ? $roleOwnerIds->push((int) $this->owner_id)->unique()->count()
            : $roleOwnerIds->unique()->count();
    }
}
