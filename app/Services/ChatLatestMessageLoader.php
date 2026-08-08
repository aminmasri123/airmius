<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;

class ChatLatestMessageLoader
{
    /**
     * Attach the latest message visible to one participant in a fixed
     * number of queries, independent of the conversation count.
     *
     * @param  Collection<int, Conversation>  $conversations
     * @param  array<int, string>  $relations
     */
    public function attach(Collection $conversations, int $userId, array $relations = []): void
    {
        if ($conversations->isEmpty()) {
            return;
        }

        $conversationIds = $conversations->pluck('id')->map(fn ($id) => (int) $id);
        $latestMessageIds = Message::query()
            ->selectRaw('MAX(messages.id) as latest_id')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('conversation_users as viewer_membership', function ($join) use ($userId) {
                $join->on('viewer_membership.conversation_id', '=', 'messages.conversation_id')
                    ->where('viewer_membership.user_id', '=', $userId);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.moderation_status', '!=', 'removed')
            ->whereDoesntHave('hides', fn ($hides) => $hides->where('user_id', $userId))
            ->where(function ($visibleSinceJoin) {
                $visibleSinceJoin
                    ->where('conversations.type', '!=', 'group')
                    ->orWhereNull('viewer_membership.joined_at')
                    ->orWhereColumn('messages.created_at', '>=', 'viewer_membership.joined_at');
            })
            ->groupBy('messages.conversation_id')
            ->pluck('latest_id');

        $latestMessages = Message::query()
            ->whereIn('id', $latestMessageIds)
            ->with($relations)
            ->get()
            ->keyBy('conversation_id');

        $conversations->each(fn (Conversation $conversation) => $conversation->setRelation(
            'latestVisibleMessage',
            $latestMessages->get($conversation->id),
        ));
    }
}
