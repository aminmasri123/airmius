<?php

namespace App\Services;

use App\Models\Message;

class ChatService
{
    public function sendMessage($user, $conversationId, $text)
    {
        return Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'message' => $text,
        ]);
    }
}
