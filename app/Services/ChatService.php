<?php

namespace App\Services;

use App\Events\ChatConversationUpdated;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\File;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(private MediaOptimizer $mediaOptimizer) {}

    public function findOrCreateDirectConversation(
        User $first,
        User $second,
        ?int $clubId = null,
    ): Conversation {
        abort_if($first->id === $second->id, 422, 'Ein Direktchat benötigt zwei verschiedene Personen.');

        return DB::transaction(function () use ($first, $second, $clubId) {
            $conversation = Conversation::query()
                ->where('type', 'direct')
                ->whereHas('users', fn ($query) => $query->where('users.id', $first->id))
                ->whereHas('users', fn ($query) => $query->where('users.id', $second->id))
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'club_id' => $clubId,
                ]);
                $conversation->users()->attach([
                    $first->id => ['joined_at' => now()],
                    $second->id => ['joined_at' => now()],
                ]);
            } else {
                $conversation->touch();
            }

            return $conversation->fresh(['users']);
        });
    }

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
            $conversation->touch();
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

            $this->broadcastSafely(function () use ($message, $conversation, $user) {
                broadcast(new MessageSent($message))->toOthers();
                broadcast(new ChatConversationUpdated(
                    $conversation,
                    'message.sent',
                    $message->receipts->pluck('user_id')->push($user->id)->all()
                ))->toOthers();
            });

            return $message;
        });
    }

    private function broadcastSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('Chat realtime broadcast failed; message was saved.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
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
