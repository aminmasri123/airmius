<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Events\ChatConversationUpdated;
use App\Models\File;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(private MediaOptimizer $mediaOptimizer) {}

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
                $optimized = $this->mediaOptimizer->store($attachment, $this->directoryFor($user->id, $conversation, $event));

                $file = File::create([
                    'club_id' => $conversation->club_id,
                    'team_id' => $conversation->team_id,
                    'event_id' => $event?->id,
                    'user_id' => $user->id,
                    'display_name' => $this->displayNameForUpload($attachment),
                    ...$optimized,
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
            broadcast(new ChatConversationUpdated(
                $conversation,
                'message.sent',
                $message->receipts->pluck('user_id')->push($user->id)->all()
            ))->toOthers();

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

    private function displayNameForUpload(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $name));

        return Str::limit($name !== '' ? $name : 'Datei', 180, '');
    }
}
