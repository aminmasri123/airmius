<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\File;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function sendMessage($user, $conversationId, ?string $text, array $attachments = [])
    {
        return DB::transaction(function () use ($user, $conversationId, $text, $attachments) {
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'message' => $text,
                'status' => 'sent',
            ]);

            $conversation = $message->conversation;
            $event = $conversation->event;

            foreach ($attachments as $attachment) {
                $path = $attachment->store($this->directoryFor($user->id, $conversation, $event), 'public');
                $file = File::create([
                    'club_id' => $conversation->club_id,
                    'team_id' => $conversation->team_id,
                    'event_id' => $event?->id,
                    'user_id' => $user->id,
                    'path' => $path,
                    'type' => $attachment->getMimeType() ?: 'application/octet-stream',
                    'size' => $attachment->getSize(),
                ]);

                $message->attachments()->create(['file_id' => $file->id]);
            }

            $message->conversation
                ->users()
                ->where('users.id', '!=', $user->id)
                ->pluck('users.id')
                ->each(fn ($recipientId) => $message->receipts()->create([
                    'user_id' => $recipientId,
                ]));

            $message->load(['sender', 'receipts', 'attachments.file', 'reactions.user']);

            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });
    }

    private function directoryFor(int $userId, $conversation, $event): string
    {
        if ($event) {
            return 'events/'.$event->id.'/chat';
        }

        if ($conversation->team_id) {
            return 'teams/'.$conversation->team_id.'/chat';
        }

        if ($conversation->club_id) {
            return 'clubs/'.$conversation->club_id.'/chat';
        }

        return 'users/'.$userId.'/chat';
    }
}
