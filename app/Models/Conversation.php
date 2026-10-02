<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['club_id','team_id','owner_id','type','name','description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_users')
            ->withPivot('joined_at', 'muted_until', 'cleared_at', 'cleared_message_id');
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
}
