<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function sendMessage($user, $conversationId, $text)
    {
        return DB::transaction(function () use ($user, $conversationId, $text) {
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'message' => $text,
                'status' => 'sent',
            ]);

            $message->conversation
                ->users()
                ->where('users.id', '!=', $user->id)
                ->pluck('users.id')
                ->each(fn ($recipientId) => $message->receipts()->create([
                    'user_id' => $recipientId,
                ]));

            return $message->load(['sender', 'receipts']);
        });
    }
}
