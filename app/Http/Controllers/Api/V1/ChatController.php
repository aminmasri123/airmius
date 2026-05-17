<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChatTyping;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Services\ChatService;
use App\Services\ModerationService;
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

    private function authorizeParticipant(Conversation $conversation, Request $request): void
    {
        abort_unless(
            $conversation->users()->where('users.id', $request->user()->id)->exists(),
            403
        );
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 25), 1), 100);
    }
}
