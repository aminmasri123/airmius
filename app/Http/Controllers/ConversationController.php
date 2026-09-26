<?php

namespace App\Http\Controllers;

use App\Events\ChatConversationUpdated;
use App\Events\ChatTyping;
use App\Events\MessageReceiptsUpdated;
use App\Models\Conversation;
use App\Models\ConversationInvitation;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Models\Team;
use App\Models\User;
use App\Services\ChatLatestMessageLoader;
use App\Services\ChatService;
use App\Services\ModerationService;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private ModerationService $moderation,
        private ChatLatestMessageLoader $latestMessageLoader,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selected = null;

        if ($request->filled('conversation')) {
            $selected = Conversation::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->find($request->integer('conversation'));
        }

        return $this->renderIndex($selected, $request);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
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

        $participantIds = collect($data['participant_ids'] ?? [])
            ->push(auth()->id())
            ->unique()
            ->values();

        abort_if($request->user()->hasRole('minor_pending_consent'), 403, __('server.guardian.consent_required'));
        abort_if($data['type'] === 'direct' && $participantIds->count() !== 2, 422);
        abort_if($data['type'] === 'group' && $participantIds->count() < 3, 422);

        if ($data['type'] === 'team') {
            $team = Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->findOrFail($data['team_id']);

            $conversation = Conversation::firstOrCreate(
                ['type' => 'team', 'team_id' => $team->id],
                ['club_id' => $team->club_id]
            );
            $this->attachNewParticipantsWithJoinedAt(
                $conversation,
                $team->users()->pluck('users.id')->push(auth()->id())->unique()
            );
        } elseif ($data['type'] === 'direct') {
            $this->authorizeClubContext($data['club_id'] ?? null, $participantIds);
            $recipient = User::findOrFail($participantIds->first(fn ($id) => $id !== auth()->id()));

            abort_unless($recipient->allowsDirectMessagesFrom($request->user()), 403, __('server.chat.direct_messages_forbidden'));

            $conversation = Conversation::query()
                ->where('type', 'direct')
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->whereHas('users', fn ($query) => $query->where('users.id', $recipient->id))
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'club_id' => $data['club_id'] ?? null,
                ]);
                $conversation->users()->attach($participantIds, ['joined_at' => now()]);
            }
        } else {
            $this->authorizeClubContext($data['club_id'] ?? null, $participantIds);
            $this->authorizeGroupParticipants($request, $participantIds->reject(fn ($id) => (int) $id === auth()->id()));

            $conversation = Conversation::create([
                'type' => 'group',
                'club_id' => $data['club_id'] ?? null,
                'owner_id' => auth()->id(),
                'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            ]);
            $conversation->users()->attach($participantIds, ['joined_at' => now()]);
            $this->addSystemMessage(
                $conversation,
                $request->user(),
                $request->user()->name.' hat die Gruppe erstellt.',
                'group.created'
            );
        }

        if (! empty($data['message'])) {
            $message = $this->chatService->sendMessage(auth()->user(), $conversation->id, $data['message']);
            $this->moderation->flagIfNeeded($message, $message->message, auth()->id());
        }

        return redirect()
            ->route('auth.conversations.index', ['conversation' => $conversation->id])
            ->with('success', __('server.chat.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);

        return $this->renderIndex($conversation, request());
    }

    public function typing(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);

        $data = $request->validate([
            'typing' => ['nullable', 'boolean'],
        ]);

        $typing = $data['typing'] ?? true;
        $cacheKey = $this->typingCacheKey($conversation->id, $request->user()->id);

        if ($typing) {
            Cache::put($cacheKey, true, now()->addSeconds(6));
        } else {
            Cache::forget($cacheKey);
        }

        broadcast(new ChatTyping($conversation, $request->user(), $typing))->toOthers();

        return response()->json(['success' => true]);
    }

    public function addMembers(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($conversation->type !== 'group', 422, __('server.chat.members_group_only'));
        abort_unless($this->canManageGroup($conversation), 403);

        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $participantIds = collect($data['participant_ids'])
            ->reject(fn ($id) => (int) $id === auth()->id())
            ->unique()
            ->values();

        abort_if($participantIds->isEmpty(), 422, __('server.chat.select_participant'));

        $this->authorizeGroupParticipants($request, $participantIds);
        $existingIds = $conversation->users()
            ->whereIn('users.id', $participantIds)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        $invitedIds = $participantIds->diff($existingIds)->values();

        $invitedIds->each(function (int $recipientId) use ($conversation, $request) {
            $invitation = ConversationInvitation::updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'recipient_id' => $recipientId,
                ],
                [
                    'inviter_id' => $request->user()->id,
                    'status' => 'pending',
                    'responded_at' => null,
                ]
            );

            AppNotification::send($recipientId, 'chat.group_invite', [
                'title' => $request->user()->name.' hat dich in eine Chatgruppe eingeladen',
                'body' => $conversation->name ?: 'Neue Gruppeneinladung',
                'url' => route('auth.conversations.index'),
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'conversation_id' => $conversation->id,
                'invitation_id' => $invitation->id,
            ]);
        });

        if ($invitedIds->isNotEmpty()) {
            $inviteeNames = User::query()
                ->whereIn('id', $invitedIds)
                ->pluck('name')
                ->all();

            $this->addSystemMessage(
                $conversation,
                $request->user(),
                $request->user()->name.' hat '.implode(', ', $inviteeNames).' eingeladen.',
                'group.invitation.created',
                ['recipient_ids' => $invitedIds->all()]
            );

            broadcast(new ChatConversationUpdated($conversation, 'invitations.created', $invitedIds->all()))->toOthers();
        }

        return back()->with(
            'success',
            __($invitedIds->isEmpty() ? 'server.chat.no_new_invitations' : 'server.chat.invitations_sent')
        );
    }

    public function leave(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($conversation->type === 'direct', 422, __('server.chat.direct_cannot_leave'));

        $data = $request->validate([
            'delete_conversation' => ['nullable', 'boolean'],
        ]);

        $broadcastUserIds = $conversation->users()->pluck('users.id')->all();

        DB::transaction(function () use ($conversation, $data, $request) {
            $conversation->loadCount('users');
            $remainingAfterLeave = max(0, $conversation->users_count - 1);

            if (($data['delete_conversation'] ?? false) && $remainingAfterLeave <= 1) {
                $conversation->delete();

                return;
            }

            $remainingUserIds = $conversation->users()
                ->where('users.id', '!=', $request->user()->id)
                ->pluck('users.id');

            $this->addSystemMessage(
                $conversation,
                $request->user(),
                $request->user()->name.' hat die Gruppe verlassen.',
                'group.member.left',
                ['user_id' => $request->user()->id]
            );

            if ((int) $conversation->owner_id === (int) $request->user()->id) {
                $conversation->update(['owner_id' => $remainingUserIds->first()]);
            }

            $conversation->users()->detach(auth()->id());
        });

        broadcast(new ChatConversationUpdated($conversation, 'member.left', $broadcastUserIds))->toOthers();

        return redirect()
            ->route('auth.conversations.index')
            ->with('success', __('server.chat.left'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Conversation $conversation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($conversation->type !== 'group', 422, __('server.chat.edit_group_only'));
        abort_unless($this->canManageGroup($conversation), 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $conversation->update([
            'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ]);

        $this->addSystemMessage(
            $conversation,
            $request->user(),
            $request->user()->name.' hat das Gruppenprofil aktualisiert.',
            'group.profile.updated'
        );

        broadcast(new ChatConversationUpdated($conversation, 'profile.updated'))->toOthers();

        return back()->with('success', __('server.chat.profile_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation)
    {
        //
    }

    public function mute(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);

        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'in:0,60,480,1440,10080'],
        ]);

        $mutedUntil = ((int) ($data['minutes'] ?? 0)) > 0
            ? now()->addMinutes((int) $data['minutes'])
            : null;

        $conversation->users()->updateExistingPivot(auth()->id(), [
            'muted_until' => $mutedUntil,
        ]);

        return back()->with(
            'success',
            __($mutedUntil ? 'server.chat.muted' : 'server.chat.unmuted')
        );
    }

    public function acceptInvitation(Request $request, ConversationInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        $conversation = $invitation->conversation;

        abort_if(! $conversation || $conversation->type !== 'group', 422);
        abort_unless($conversation->users()->where('users.id', $invitation->inviter_id)->exists(), 403);

        DB::transaction(function () use ($conversation, $invitation) {
            $conversation->users()->syncWithoutDetaching([
                $invitation->recipient_id => ['joined_at' => now()],
            ]);

            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        AppNotification::send($invitation->inviter_id, 'chat.group_invite.accepted', [
            'title' => $request->user()->name.' hat deine Gruppeneinladung angenommen',
            'body' => $conversation->name ?: 'Gruppeneinladung angenommen',
            'url' => route('auth.conversations.index', ['conversation' => $conversation->id]),
            'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'conversation_id' => $conversation->id,
            'invitation_id' => $invitation->id,
        ]);

        $this->addSystemMessage(
            $conversation,
            $request->user(),
            $request->user()->name.' ist der Gruppe beigetreten.',
            'group.member.joined',
            ['user_id' => $request->user()->id]
        );

        broadcast(new ChatConversationUpdated($conversation, 'member.joined', [
            $invitation->recipient_id,
            $invitation->inviter_id,
        ]))->toOthers();

        return redirect()
            ->route('auth.conversations.index', ['conversation' => $conversation->id])
            ->with('success', __('server.chat.invitation_accepted'));
    }

    public function declineInvitation(Request $request, ConversationInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        $conversation = $invitation->conversation;

        if ($conversation) {
            $this->addSystemMessage(
                $conversation,
                $request->user(),
                $request->user()->name.' hat die Gruppeneinladung abgelehnt.',
                'group.invitation.declined',
                ['user_id' => $request->user()->id]
            );

            broadcast(new ChatConversationUpdated($conversation, 'invitation.declined', [
                $invitation->recipient_id,
                $invitation->inviter_id,
            ]))->toOthers();
        }

        return back()->with('success', __('server.chat.invitation_declined'));
    }

    public function removeMember(Request $request, Conversation $conversation, User $user)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($conversation->type !== 'group', 422, __('server.chat.remove_group_only'));
        abort_unless($this->canManageGroup($conversation), 403);
        abort_if($user->id === auth()->id(), 422, __('server.chat.remove_self'));
        abort_if((int) $conversation->owner_id === (int) $user->id, 422, __('server.chat.remove_owner'));
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 404);

        $conversation->users()->detach($user->id);

        $this->addSystemMessage(
            $conversation,
            $request->user(),
            $request->user()->name.' hat '.$user->name.' aus der Gruppe entfernt.',
            'group.member.removed',
            ['user_id' => $user->id]
        );

        broadcast(new ChatConversationUpdated($conversation, 'member.removed', [$user->id]))->toOthers();

        AppNotification::sendLocalized(
            $user,
            'chat.group_removed',
            'platform.chat.removed_title',
            'platform.chat.group_body',
            [
                'conversation' => $conversation->name ?: AppNotification::translatedReplacement('platform.chat.group_fallback', 'Group chat'),
            ],
            [
                'url' => route('auth.conversations.index'),
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'conversation_id' => $conversation->id,
            ],
        );

        return back()->with('success', __('server.chat.member_removed'));
    }

    public function transferOwner(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($conversation->type !== 'group', 422, __('server.chat.transfer_group_only'));
        abort_unless($this->canManageGroup($conversation), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        abort_if((int) $data['user_id'] === auth()->id(), 422, __('server.chat.already_owner'));
        abort_unless($conversation->users()->where('users.id', $data['user_id'])->exists(), 422, __('server.chat.owner_must_be_member'));

        $conversation->update(['owner_id' => (int) $data['user_id']]);
        $newOwner = User::find((int) $data['user_id']);

        $this->addSystemMessage(
            $conversation,
            $request->user(),
            $request->user()->name.' hat '.$newOwner?->name.' zum Owner gemacht.',
            'group.owner.transferred',
            ['user_id' => (int) $data['user_id']]
        );

        broadcast(new ChatConversationUpdated($conversation, 'owner.transferred', [(int) $data['user_id']]))->toOthers();

        AppNotification::sendLocalized(
            (int) $data['user_id'],
            'chat.group_owner_transferred',
            'platform.chat.owner_title',
            'platform.chat.group_body',
            [
                'conversation' => $conversation->name ?: AppNotification::translatedReplacement('platform.chat.group_fallback', 'Group chat'),
            ],
            [
                'url' => route('auth.conversations.index', ['conversation' => $conversation->id]),
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'conversation_id' => $conversation->id,
            ],
        );

        return back()->with('success', __('server.chat.owner_transferred'));
    }

    private function renderIndex(?Conversation $selectedConversation = null, ?Request $request = null)
    {
        $request ??= request();
        $messageLimit = min(500, max(50, (int) $request->integer('message_limit', 50)));
        $messageSearch = trim((string) $request->query('message_search', ''));
        $userConversationIds = Conversation::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
            ->pluck('id');

        MessageReceipt::query()
            ->where('user_id', auth()->id())
            ->whereNull('delivered_at')
            ->whereHas('message', fn ($query) => $query
                ->whereIn('conversation_id', $userConversationIds)
                ->visibleSinceGroupJoin((int) auth()->id()))
            ->update(['delivered_at' => now()]);

        $conversations = Conversation::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
            ->with(['users:id,name', 'owner:id,name', 'team:id,name', 'event:id,conversation_id,title'])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query->visibleSinceGroupJoin((int) auth()->id())->whereHas(
                    'receipts',
                    fn ($receiptQuery) => $receiptQuery
                        ->where('user_id', auth()->id())
                        ->whereNull('read_at')
                ),
            ])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        $this->latestMessageLoader->attach($conversations, (int) auth()->id(), [
            'sender:id,name',
            'attachments.file:id,display_name,path,thumbnail_path,type,size',
        ]);

        $readConversationIds = $selectedConversation
            ? collect([$selectedConversation->id])
            : collect();

        if ($readConversationIds->isNotEmpty()) {
            $readMessageIds = MessageReceipt::query()
                ->where('user_id', auth()->id())
                ->whereNull('read_at')
                ->whereHas('message', function ($query) use ($readConversationIds, $selectedConversation) {
                    $query->whereIn('conversation_id', $readConversationIds);

                    $this->onlyMessagesVisibleSinceGroupJoin($query, $selectedConversation);
                })
                ->pluck('message_id')
                ->all();

            MessageReceipt::query()
                ->where('user_id', auth()->id())
                ->whereIn('message_id', $readMessageIds)
                ->whereNull('read_at')
                ->update([
                    'delivered_at' => now(),
                    'read_at' => now(),
                ]);

            if ($selectedConversation && $readMessageIds) {
                $this->broadcastSafely(
                    fn () => broadcast(new MessageReceiptsUpdated($selectedConversation, $readMessageIds))->toOthers()
                );
            }

            auth()->user()
                ->appNotifications()
                ->where('type', 'chat.message')
                ->where('read', false)
                ->when($selectedConversation, fn ($query) => $query->where('data->conversation_id', $selectedConversation->id))
                ->update(['read' => true]);
        }

        $messagePage = [
            'limit' => $messageLimit,
            'has_more' => false,
            'next_limit' => $messageLimit,
        ];

        if ($selectedConversation) {
            $selectedConversation->load([
                'users:id,name',
                'owner:id,name',
                'invitations' => fn ($query) => $query
                    ->where('status', 'pending')
                    ->with(['recipient:id,name,email', 'inviter:id,name'])
                    ->latest('id'),
                'team:id,name',
                'event:id,conversation_id,title',
            ]);

            $messagesQuery = $selectedConversation->messages()
                ->where('moderation_status', '!=', 'removed')
                ->whereDoesntHave('hides', fn ($query) => $query->where('user_id', auth()->id()))
                ->when($messageSearch !== '', fn ($query) => $query->where('message', 'like', '%'.$messageSearch.'%'))
                ->with([
                    'sender:id,name',
                    'receipts:id,message_id,user_id,delivered_at,read_at',
                    'attachments.file:id,display_name,path,thumbnail_path,type,size',
                    'reactions.user:id,name',
                ]);

            $this->onlyMessagesVisibleSinceGroupJoin($messagesQuery, $selectedConversation);

            $messages = $messagesQuery
                ->latest('id')
                ->limit($messageLimit + 1)
                ->get();

            $messagePage = [
                'limit' => $messageLimit,
                'has_more' => $messages->count() > $messageLimit,
                'next_limit' => min(500, $messageLimit + 50),
            ];

            $selectedConversation->setRelation(
                'messages',
                $messages->take($messageLimit)->sortBy('id')->values()
            );
        }

        return Inertia::render('Auth/Dashboard/Chat/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messagePage' => $messagePage,
            'messageSearch' => $messageSearch,
            'groupInvitations' => ConversationInvitation::query()
                ->where('recipient_id', auth()->id())
                ->where('status', 'pending')
                ->with([
                    'inviter:id,name',
                    'conversation' => fn ($query) => $query->with(['users:id,name', 'owner:id,name']),
                ])
                ->latest('id')
                ->get(),
            'typingUsers' => $selectedConversation
                ? $selectedConversation->users
                    ->where('id', '!=', auth()->id())
                    ->filter(fn (User $user) => Cache::has($this->typingCacheKey($selectedConversation->id, $user->id)))
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])
                    ->values()
                : [],
            'users' => User::query()
                ->whereKeyNot(auth()->id())
                ->whereDoesntHave('blockedUsers', fn ($query) => $query->where('blocked_user_id', auth()->id()))
                ->whereDoesntHave('blockedByUsers', fn ($query) => $query->where('user_id', auth()->id()))
                ->where(function ($query) {
                    $query->whereHas('friendships', function ($q) {
                        $q->where('friend_id', auth()->id());
                    })
                        ->orWhereHas('receivedFriendships', function ($q) {
                            $q->where('user_id', auth()->id());
                        });
                })
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->limit(100)
                ->get(),
            'teams' => auth()->user()
                ->teams()
                ->select(['teams.id', 'teams.name', 'teams.club_id'])
                ->orderBy('teams.name')
                ->get(),
        ]);
    }

    private function broadcastSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('Chat realtime broadcast failed; the chat action was saved.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function typingCacheKey(int $conversationId, int $userId): string
    {
        return "chat:typing:{$conversationId}:{$userId}";
    }

    private function participantsWithJoinedAt($participantIds): array
    {
        $joinedAt = now();

        return collect($participantIds)
            ->unique()
            ->mapWithKeys(fn ($id) => [(int) $id => ['joined_at' => $joinedAt]])
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

    private function addSystemMessage(Conversation $conversation, User $actor, string $text, string $event, array $metadata = []): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $actor->id,
            'message' => $text,
            'kind' => 'system',
            'metadata' => [
                'event' => $event,
                'actor_id' => $actor->id,
                ...$metadata,
            ],
            'status' => 'sent',
        ]);

        $conversation->users()
            ->where('users.id', '!=', $actor->id)
            ->pluck('users.id')
            ->each(fn ($recipientId) => $message->receipts()->create([
                'user_id' => $recipientId,
            ]));

        return $message;
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

        abort_if($participants->count() !== $participantIds->count(), 422, __('server.chat.participants_missing'));

        $actor = $request->user();

        foreach ($participants as $participant) {
            abort_unless(
                $actor->isFriendsWith($participant) && $participant->allowsDirectMessagesFrom($actor),
                403,
                __('server.chat.group_friends_only')
            );
        }
    }

    private function authorizeClubContext(?int $clubId, $participantIds): void
    {
        if (! $clubId) {
            return;
        }

        $participantIds = collect($participantIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $activeMembers = DB::table('club_user')
            ->where('club_id', $clubId)
            ->whereIn('user_id', $participantIds)
            ->where(function ($query) {
                $query->whereNull('membership_status')
                    ->orWhere('membership_status', 'active');
            })
            ->distinct()
            ->count('user_id');

        abort_unless($activeMembers === $participantIds->count(), 422, __('server.chat.club_context_members_only'));
    }

    private function canManageGroup(Conversation $conversation): bool
    {
        return ! $conversation->owner_id || (int) $conversation->owner_id === auth()->id();
    }

    private function onlyMessagesVisibleSinceGroupJoin($query, ?Conversation $conversation): void
    {
        if (! $conversation || $conversation->type !== 'group') {
            return;
        }

        $joinedAt = DB::table('conversation_users')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', auth()->id())
            ->value('joined_at');

        if ($joinedAt) {
            $query->where('created_at', '>=', $joinedAt);
        }
    }
}
