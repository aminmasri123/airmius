<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['sender', 'attachments.file', 'reactions.user']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'kind' => $this->message->kind,
                'metadata' => $this->message->metadata,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                ],
                'message' => $this->message->message,
                'attachments' => $this->message->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'file_id' => $attachment->file_id,
                    'file' => $attachment->file ? [
                        'id' => $attachment->file->id,
                        'display_name' => $attachment->file->display_name,
                        'path' => $attachment->file->path,
                        'thumbnail_path' => $attachment->file->thumbnail_path,
                        'type' => $attachment->file->type,
                        'size' => $attachment->file->size,
                    ] : null,
                ])->values(),
                'reactions' => $this->message->reactions->map(fn ($reaction) => [
                    'id' => $reaction->id,
                    'user_id' => $reaction->user_id,
                    'reaction' => $reaction->reaction,
                    'user' => [
                        'id' => $reaction->user?->id,
                        'name' => $reaction->user?->name,
                    ],
                ])->values(),
                'created_at' => $this->message->created_at,
                'delivery_status' => 'sent',
            ],
        ];
    }
}
