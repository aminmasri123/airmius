<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChatTyping;
use App\Events\MessageDeleted;
use App\Events\MessageReactionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\MessageReaction;
use App\Services\ChatService;
use App\Services\ModerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\UploadStorage;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ModerationService $moderation,
    ) {}

    public function index(Request $request)
    {
        $conversations = $request->user()
            ->conversations()
            ->with(['users', 'team', 'owner'])
            ->withCount('messages')
            ->orderByDesc('conversations.updated_at')
            ->paginate($this->perPage($request));

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        return new ConversationResource(
            $conversation->loadMissing(['users', 'team', 'owner'])->loadCount('messages')
        );
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        $messages = $conversation->messages()
            ->whereDoesntHave('hides', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['sender', 'receipts', 'attachments.file', 'reactions.user'])
            ->latest()
            ->paginate($this->perPage($request));

        return MessageResource::collection($messages);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        $data = $request->validate([
            'message' => ['nullable', 'required_without:attachments', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if ($conversation->type === 'direct') {
            $recipient = $conversation->users()
                ->where('users.id', '!=', $request->user()->id)
                ->first();

            abort_unless($recipient?->allowsDirectMessagesFrom($request->user()), 403, 'Diese Person erlaubt keine Nachrichten von dir.');
        }

        $message = $this->chatService->sendMessage(
            $request->user(),
            $conversation->id,
            $data['message'] ?? null,
            $request->file('attachments', [])
        );

        $this->moderation->flagIfNeeded($message, $message->message, $request->user()->id);

        return (new MessageResource(
            $message->loadMissing(['sender', 'receipts', 'attachments.file', 'reactions.user'])
        ))->response()->setStatusCode(201);
    }

    public function typing(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        $data = $request->validate([
            'typing' => ['required', 'boolean'],
        ]);

        broadcast(new ChatTyping($conversation, $request->user(), (bool) $data['typing']))->toOthers();

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'typing' => (bool) $data['typing'],
            ],
        ]);
    }

    public function react(Request $request, Message $message)
    {
        $this->authorizeMessageAccess($message, $request);

        $data = $request->validate([
            'reaction' => ['required', 'in:like,heart,ok'],
        ]);

        $reaction = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($reaction?->reaction === $data['reaction']) {
            $reaction->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $request->user()->id],
                ['reaction' => $data['reaction']]
            );
        }

        $message->load('reactions.user');
        $this->broadcastSafely(fn () => broadcast(new MessageReactionUpdated($message))->toOthers());

        return response()->json([
            'success' => true,
            'reactions' => $message->reactions,
        ]);
    }

    public function hideForMe(Request $request, Message $message)
    {
        $this->authorizeMessageAccess($message, $request);

        MessageHide::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteMessage(Request $request, Message $message)
    {
        $this->authorizeMessageAccess($message, $request);
        abort_unless($message->sender_id === $request->user()->id || $request->user()->can('user.manage'), 403);
        abort_if($message->receipts()->whereNotNull('read_at')->exists(), 422, 'Diese Nachricht wurde bereits gelesen und kann nicht mehr geloescht werden.');

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
        $this->broadcastSafely(fn () => broadcast(new MessageDeleted($message))->toOthers());

        return response()->json(['success' => true]);
    }

    private function authorizeParticipant(Conversation $conversation, Request $request): void
    {
        abort_unless(
            $conversation->users()->where('users.id', $request->user()->id)->exists(),
            403
        );
    }

    private function authorizeMessageAccess(Message $message, Request $request): void
    {
        $conversation = $message->conversation;
        $this->authorizeParticipant($conversation, $request);

        if ($conversation->type !== 'group') {
            return;
        }

        $joinedAt = DB::table('conversation_users')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $request->user()->id)
            ->value('joined_at');

        abort_if($joinedAt && $message->created_at->lessThan($joinedAt), 403);
    }

    private function broadcastSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable) {
            // Realtime failures must not break mobile chat actions.
        }
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 25), 1), 100);
    }
}
