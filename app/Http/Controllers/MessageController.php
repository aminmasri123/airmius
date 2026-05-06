<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageReactionUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Models\MessageReaction;
use App\Services\ChatService;
use App\Services\ModerationService;
use App\Support\AppNotification;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(
        private ChatService $service,
        private ModerationService $moderation,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'message' => ['nullable', 'required_without:attachments', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);

        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);

        if ($conversation->type === 'direct') {
            $recipient = $conversation->users()
                ->where('users.id', '!=', auth()->id())
                ->first();

            abort_unless($recipient?->allowsDirectMessagesFrom($request->user()), 403, 'Diese Person erlaubt keine Nachrichten von dir.');
        }

        $message = $this->service->sendMessage(
            auth()->user(),
            $data['conversation_id'],
            $data['message'] ?? null,
            $request->file('attachments', [])
        );
        $this->moderation->flagIfNeeded($message, $message->message, auth()->id());

        $conversation->users()
            ->where('users.id', '!=', auth()->id())
            ->get()
            ->each(fn ($recipient) => AppNotification::send($recipient, 'chat.message', [
                'title' => 'Neue Nachricht von '.auth()->user()->name,
                'body' => str($message->message ?: 'Dateianhang')->limit(120)->toString(),
                'url' => route('auth.conversations.index', ['conversation' => $conversation->id]),
                'actor_id' => auth()->id(),
                'actor_name' => auth()->user()->name,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message->load(['sender', 'receipts', 'attachments.file', 'reactions.user']),
                'success' => true,
            ]);
        }

        return redirect()->route('auth.conversations.index', [
            'conversation' => $data['conversation_id'],
        ]);
    }

    public function markAsRead(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);

        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);

        MessageReceipt::query()
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $data['conversation_id']))
            ->update([
                'delivered_at' => now(),
                'read_at' => now(),
            ]);

        $this->markChatNotificationsAsRead($request, (int) $data['conversation_id']);

        return response()->json(['success' => true]);
    }

    public function destroy(Message $message)
    {
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_unless($message->sender_id === auth()->id() || auth()->user()->can('user.manage'), 403);
        abort_if(
            $message->receipts()->whereNotNull('read_at')->exists(),
            422,
            'Diese Nachricht wurde bereits gelesen und kann nicht mehr gelöscht werden.'
        );

        $message->load('attachments.file');

        foreach ($message->attachments as $attachment) {
            if ($attachment->file?->path) {
                Storage::disk(UploadStorage::disk())->delete(array_filter([
                    $attachment->file->path,
                    $attachment->file->thumbnail_path,
                ]));
            }

            $attachment->file?->delete();
            $attachment->delete();
        }

        $message->delete();

        broadcast(new MessageDeleted($message))->toOthers();

        return response()->json(['success' => true]);
    }

    public function react(Request $request, Message $message)
    {
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);

        $data = $request->validate([
            'reaction' => ['required', 'in:like,heart,ok'],
        ]);

        $reaction = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($reaction?->reaction === $data['reaction']) {
            $reaction->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => auth()->id()],
                ['reaction' => $data['reaction']]
            );
        }

        $message->load('reactions.user');

        broadcast(new MessageReactionUpdated($message))->toOthers();

        return response()->json([
            'success' => true,
            'reactions' => $message->reactions,
        ]);
    }

    private function markChatNotificationsAsRead(Request $request, int $conversationId): void
    {
        $request->user()
            ->appNotifications()
            ->where('type', 'chat.message')
            ->where('read', false)
            ->where('data->conversation_id', $conversationId)
            ->update(['read' => true]);
    }
}
