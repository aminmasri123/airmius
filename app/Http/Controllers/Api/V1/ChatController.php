<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChatTyping;
use App\Events\MessageDeleted;
use App\Events\MessageReactionUpdated;
use App\Events\MessageReceiptsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ConversationController as WebConversationController;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Models\ConversationInvitation;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\MessageReaction;
use App\Models\MessageReceipt;
use App\Models\Team;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ModerationService;
use App\Support\AppNotification;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ModerationService $moderation,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $userId = $request->user()->id;
        $conversationsQuery = $request->user()
            ->conversations()
            ->with(['users', 'team', 'owner'])
            ->withCount([
                'messages',
                'messages as unread_messages_count' => fn ($query) => $query
                    ->whereHas('receipts', fn ($receipts) => $receipts
                        ->where('user_id', $userId)
                        ->whereNull('read_at')),
            ])
            ->orderByDesc('conversations.updated_at');

        if ($request->filled('team_id')) {
            $conversationsQuery->where(
                'conversations.team_id',
                (int) $request->input('team_id'),
            );
        }

        $conversations = $conversationsQuery->paginate($this->perPage($request));
        $this->attachLatestVisibleMessages(
            $conversations->getCollection(),
            (int) $request->user()->id,
        );

        return ConversationResource::collection($conversations);
    }

    public function invitations(Request $request)
    {
        $invitations = ConversationInvitation::query()
            ->where('recipient_id', $request->user()->id)
            ->where('status', 'pending')
            ->with([
                'inviter:id,name',
                'conversation' => fn ($query) => $query->with(['users:id,name', 'owner:id,name']),
            ])
            ->latest('id')
            ->get()
            ->map(fn (ConversationInvitation $invitation) => [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'created_at' => optional($invitation->created_at)->toIso8601String(),
                'inviter' => $invitation->inviter ? [
                    'id' => $invitation->inviter->id,
                    'name' => $invitation->inviter->name,
                ] : null,
                'conversation' => $invitation->conversation ? [
                    'id' => $invitation->conversation->id,
                    'name' => $invitation->conversation->name,
                    'description' => $invitation->conversation->description,
                    'members_count' => $invitation->conversation->users->count(),
                ] : null,
            ])
            ->values();

        return response()->json(['data' => $invitations]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        return new ConversationResource(
            $conversation->loadMissing(['users', 'team', 'owner'])->loadCount('messages')
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:direct,group,team'],
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:type,team', 'exists:teams,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        $conversation = DB::transaction(function () use ($request, $data) {
            $participantIds = collect($data['participant_ids'] ?? [])
                ->push($request->user()->id)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            abort_if($data['type'] === 'direct' && $participantIds->count() !== 2, 422);
            abort_if($data['type'] === 'group' && $participantIds->count() < 3, 422);

            if ($data['type'] === 'team') {
                $team = Team::query()
                    ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                    ->findOrFail($data['team_id']);

                $conversation = Conversation::firstOrCreate(
                    ['type' => 'team', 'team_id' => $team->id],
                    ['club_id' => $team->club_id]
                );
                $this->attachNewParticipantsWithJoinedAt(
                    $conversation,
                    $team->users()->pluck('users.id')->push($request->user()->id)
                );
            } elseif ($data['type'] === 'direct') {
                $recipient = User::findOrFail($participantIds->first(fn ($id) => $id !== $request->user()->id));

                abort_unless($recipient->allowsDirectMessagesFrom($request->user()), 403, 'Diese Person erlaubt keine Nachrichten von dir.');

                $conversation = Conversation::query()
                    ->where('type', 'direct')
                    ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                    ->whereHas('users', fn ($query) => $query->where('users.id', $recipient->id))
                    ->first();

                if (! $conversation) {
                    $conversation = Conversation::create([
                        'type' => 'direct',
                        'club_id' => $data['club_id'] ?? null,
                    ]);
                    $conversation->users()->attach($this->participantsWithJoinedAt($participantIds));
                }
            } else {
                $this->authorizeGroupParticipants($request, $participantIds->reject(fn ($id) => (int) $id === $request->user()->id));

                $conversation = Conversation::create([
                    'type' => 'group',
                    'club_id' => $data['club_id'] ?? null,
                    'owner_id' => $request->user()->id,
                    'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
                    'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                ]);
                $conversation->users()->attach($this->participantsWithJoinedAt($participantIds));
            }

            if (! empty($data['message'])) {
                $message = $this->chatService->sendMessage($request->user(), $conversation->id, $data['message']);
                $this->moderation->flagIfNeeded($message, $message->message, $request->user()->id);
            }

            return $conversation;
        });

        return (new ConversationResource(
            $conversation->fresh()->loadMissing(['users', 'team', 'owner'])->loadCount('messages')
        ))->response()->setStatusCode(201);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        $messages = $conversation->messages()
            ->whereDoesntHave('hides', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['sender', 'receipts', 'attachments.file', 'reactions.user'])
            ->latest()
            ->paginate($this->perPage($request));

        return MessageResource::collection($messages)->additional([
            'chat' => [
                'typing_users' => $this->typingUsers($conversation, $request),
            ],
        ]);
    }

    /**
     * Resolve a protected message deep link without exposing messages that the
     * current participant has hidden or could not see when joining a group.
     */
    public function message(Request $request, Message $message)
    {
        $this->authorizeMessageAccess($message, $request);

        abort_if(
            $message->hides()->where('user_id', $request->user()->id)->exists(),
            404,
            'Diese Nachricht ist nicht verfügbar.'
        );

        return new MessageResource(
            $message->loadMissing([
                'sender',
                'conversation',
                'receipts',
                'attachments.file',
                'reactions.user',
            ])
        );
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

        $sender = $request->user();
        $conversation->users()
            ->where('users.id', '!=', $sender->id)
            ->get()
            ->each(function (User $recipient) use ($conversation, $message, $sender) {
                if ($this->recipientHasMutedConversation($recipient)) {
                    return;
                }

                AppNotification::send($recipient, 'chat.message', [
                    'title' => 'Neue Nachricht von '.$sender->name,
                    'body' => str($message->message ?: 'Dateianhang')->limit(120)->toString(),
                    'url' => route('auth.conversations.index', ['conversation' => $conversation->id]),
                    'actor_id' => $sender->id,
                    'actor_name' => $sender->name,
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ]);
            });

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

        $cacheKey = $this->typingCacheKey($conversation->id, $request->user()->id);
        if ((bool) $data['typing']) {
            Cache::put($cacheKey, true, now()->addSeconds(8));
        } else {
            Cache::forget($cacheKey);
        }

        broadcast(new ChatTyping($conversation, $request->user(), (bool) $data['typing']))->toOthers();

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'typing' => (bool) $data['typing'],
                'typing_users' => $this->typingUsers($conversation, $request),
            ],
        ]);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation, $request);

        $messageIds = MessageReceipt::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))
            ->pluck('message_id')
            ->all();

        MessageReceipt::query()
            ->whereIn('message_id', $messageIds)
            ->where('user_id', $request->user()->id)
            ->update([
                'delivered_at' => now(),
                'read_at' => now(),
            ]);

        $request->user()
            ->appNotifications()
            ->where('type', 'chat.message')
            ->where('read', false)
            ->where('data->conversation_id', $conversation->id)
            ->update(['read' => true]);

        if ($messageIds) {
            $this->broadcastSafely(fn () => broadcast(new MessageReceiptsUpdated($conversation, $messageIds))->toOthers());
        }

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'read_message_ids' => $messageIds,
                'read_count' => count($messageIds),
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

    public function updateConversation(Request $request, Conversation $conversation)
    {
        app(WebConversationController::class)->update($request, $conversation);

        return new ConversationResource($this->conversationPayload($conversation));
    }

    public function muteConversation(Request $request, Conversation $conversation)
    {
        app(WebConversationController::class)->mute($request, $conversation);

        return new ConversationResource($this->conversationPayload($conversation));
    }

    public function leaveConversation(Request $request, Conversation $conversation)
    {
        app(WebConversationController::class)->leave($request, $conversation);

        return response()->json(['message' => 'Du hast die Gruppe verlassen.']);
    }

    public function inviteMembers(Request $request, Conversation $conversation)
    {
        app(WebConversationController::class)->addMembers($request, $conversation);

        return new ConversationResource($this->conversationPayload($conversation));
    }

    public function acceptInvitation(Request $request, ConversationInvitation $invitation)
    {
        app(WebConversationController::class)->acceptInvitation($request, $invitation);

        return new ConversationResource($this->conversationPayload($invitation->conversation));
    }

    public function declineInvitation(Request $request, ConversationInvitation $invitation)
    {
        app(WebConversationController::class)->declineInvitation($request, $invitation);

        return response()->json(['message' => 'Einladung abgelehnt.']);
    }

    public function removeMember(Request $request, Conversation $conversation, User $user)
    {
        app(WebConversationController::class)->removeMember($request, $conversation, $user);

        return new ConversationResource($this->conversationPayload($conversation));
    }

    public function transferOwner(Request $request, Conversation $conversation)
    {
        app(WebConversationController::class)->transferOwner($request, $conversation);

        return new ConversationResource($this->conversationPayload($conversation));
    }

    private function conversationPayload(Conversation $conversation): Conversation
    {
        return $conversation->fresh()
            ->loadMissing(['users', 'team', 'owner'])
            ->loadCount('messages');
    }

    private function attachLatestVisibleMessages($conversations, int $userId): void
    {
        if ($conversations->isEmpty()) {
            return;
        }

        $conversationIds = $conversations->pluck('id');
        $joinedAtByConversation = DB::table('conversation_users')
            ->where('user_id', $userId)
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('joined_at', 'conversation_id');

        $latestMessageIds = Message::query()
            ->selectRaw('MAX(messages.id) as id')
            ->whereIn('conversation_id', $conversationIds)
            ->where('moderation_status', '!=', 'removed')
            ->whereDoesntHave('hides', fn ($hides) => $hides->where('user_id', $userId))
            ->where(function ($visibleMessages) use ($conversations, $joinedAtByConversation) {
                $conversations->each(function (Conversation $conversation) use ($visibleMessages, $joinedAtByConversation) {
                    $visibleMessages->orWhere(function ($conversationMessages) use ($conversation, $joinedAtByConversation) {
                        $conversationMessages->where('conversation_id', $conversation->id);

                        $joinedAt = $joinedAtByConversation->get($conversation->id);
                        if ($conversation->type === 'group' && $joinedAt) {
                            $conversationMessages->where('created_at', '>=', $joinedAt);
                        }
                    });
                });
            })
            ->groupBy('conversation_id')
            ->pluck('id');

        $latestMessages = Message::query()
            ->whereIn('id', $latestMessageIds)
            ->with(['sender', 'receipts', 'attachments.file', 'reactions.user'])
            ->get()
            ->keyBy('conversation_id');

        $conversations->each(fn (Conversation $conversation) => $conversation->setRelation(
            'latestVisibleMessage',
            $latestMessages->get($conversation->id),
        ));
    }

    private function authorizeParticipant(Conversation $conversation, Request $request): void
    {
        abort_unless(
            $conversation->users()->where('users.id', $request->user()->id)->exists(),
            403
        );
    }

    private function participantsWithJoinedAt($participantIds): array
    {
        $joinedAt = now();

        return collect($participantIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => ['joined_at' => $joinedAt]])
            ->all();
    }

    private function attachNewParticipantsWithJoinedAt(Conversation $conversation, $participantIds): void
    {
        $participantIds = collect($participantIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return;
        }

        $existingIds = $conversation->users()
            ->whereIn('users.id', $participantIds)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        $newParticipantIds = $participantIds->diff($existingIds)->values();

        if ($newParticipantIds->isNotEmpty()) {
            $conversation->users()->syncWithoutDetaching($this->participantsWithJoinedAt($newParticipantIds));
        }
    }

    private function authorizeGroupParticipants(Request $request, $participantIds): void
    {
        $participantIds = collect($participantIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return;
        }

        $participants = User::query()
            ->whereIn('id', $participantIds)
            ->get()
            ->keyBy('id');

        abort_if($participants->count() !== $participantIds->count(), 422, 'Mindestens eine ausgewaehlte Person wurde nicht gefunden.');

        foreach ($participants as $participant) {
            abort_unless(
                $request->user()->isFriendsWith($participant) && $participant->allowsDirectMessagesFrom($request->user()),
                403,
                'Gruppenchats koennen nur mit Personen gestartet werden, die Nachrichten von dir erlauben und mit dir befreundet sind.'
            );
        }
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

    private function recipientHasMutedConversation(User $recipient): bool
    {
        $mutedUntil = $recipient->pivot?->muted_until;

        return $mutedUntil && Carbon::parse($mutedUntil)->isFuture();
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

    private function typingUsers(Conversation $conversation, Request $request): array
    {
        return $conversation->users()
            ->where('users.id', '!=', $request->user()->id)
            ->get(['users.id', 'users.name'])
            ->filter(fn ($user) => Cache::has($this->typingCacheKey($conversation->id, $user->id)))
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }

    private function typingCacheKey(int $conversationId, int $userId): string
    {
        return "chat:typing:{$conversationId}:{$userId}";
    }
}
