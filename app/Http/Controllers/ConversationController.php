<?php

namespace App\Http\Controllers;

use App\Events\ChatTyping;
use App\Models\Conversation;
use App\Models\MessageReceipt;
use App\Models\Team;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private ModerationService $moderation,
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
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        $participantIds = collect($data['participant_ids'] ?? [])
            ->push(auth()->id())
            ->unique()
            ->values();

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
            $recipient = User::findOrFail($participantIds->first(fn ($id) => $id !== auth()->id()));

            abort_unless($recipient->allowsDirectMessagesFrom($request->user()), 403, 'Diese Person erlaubt keine Nachrichten von dir.');

            $conversation = Conversation::query()
                ->where('type', 'direct')
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->whereHas('users', fn ($query) => $query->where('users.id', $recipient->id))
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'club_id' => $data['club_id'] ?? null,
                ]);
                $conversation->users()->attach($participantIds, ['joined_at' => now()]);
            }
        } else {
            $conversation = Conversation::create([
                'type' => 'group',
                'club_id' => $data['club_id'] ?? null,
            ]);
            $conversation->users()->attach($participantIds, ['joined_at' => now()]);
        }

        if (!empty($data['message'])) {
            $message = $this->chatService->sendMessage(auth()->user(), $conversation->id, $data['message']);
            $this->moderation->flagIfNeeded($message, $message->message, auth()->id());
        }

        return redirect()
            ->route('auth.conversations.index', ['conversation' => $conversation->id])
            ->with('success', 'Konversation erstellt.');
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
        abort_if($conversation->type !== 'group', 422, 'Mitglieder koennen nur zu Gruppenchats hinzugefuegt werden.');

        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $participantIds = collect($data['participant_ids'])
            ->reject(fn ($id) => (int) $id === auth()->id())
            ->unique()
            ->values();

        abort_if($participantIds->isEmpty(), 422, 'Bitte mindestens eine weitere Person auswaehlen.');

        $this->attachNewParticipantsWithJoinedAt($conversation, $participantIds);

        return back()->with('success', 'Mitglieder wurden hinzugefuegt.');
    }

    public function leave(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($conversation->type === 'direct', 422, 'Direktchats können nicht verlassen werden.');

        $data = $request->validate([
            'delete_conversation' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($conversation, $data) {
            $conversation->loadCount('users');
            $remainingAfterLeave = max(0, $conversation->users_count - 1);

            if (($data['delete_conversation'] ?? false) && $remainingAfterLeave <= 1) {
                $conversation->delete();

                return;
            }

            $conversation->users()->detach(auth()->id());
        });

        return redirect()
            ->route('auth.conversations.index')
            ->with('success', 'Du hast die Gruppe verlassen.');
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation)
    {
        //
    }

    private function renderIndex(?Conversation $selectedConversation = null, ?Request $request = null)
    {
        $request ??= request();
        $messageLimit = min(500, max(50, (int) $request->integer('message_limit', 50)));
        $userConversationIds = Conversation::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
            ->pluck('id');

        MessageReceipt::query()
            ->where('user_id', auth()->id())
            ->whereNull('delivered_at')
            ->whereHas('message', fn ($query) => $query->whereIn('conversation_id', $userConversationIds))
            ->update(['delivered_at' => now()]);

        $conversations = Conversation::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
            ->with(['users:id,name', 'team:id,name', 'event:id,conversation_id,title'])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        $readConversationIds = $selectedConversation
            ? collect([$selectedConversation->id])
            : $userConversationIds;

        if ($readConversationIds->isNotEmpty()) {
            MessageReceipt::query()
                ->where('user_id', auth()->id())
                ->whereNull('read_at')
                ->whereHas('message', function ($query) use ($readConversationIds, $selectedConversation) {
                    $query->whereIn('conversation_id', $readConversationIds);

                    $this->onlyMessagesVisibleSinceGroupJoin($query, $selectedConversation);
                })
                ->update([
                    'delivered_at' => now(),
                    'read_at' => now(),
                ]);

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
                'team:id,name',
                'event:id,conversation_id,title',
            ]);

            $messagesQuery = $selectedConversation->messages()
                ->where('moderation_status', '!=', 'removed')
                ->with([
                    'sender:id,name',
                    'receipts:id,message_id,user_id,delivered_at,read_at',
                    'attachments.file:id,path,thumbnail_path,type,size',
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

    private function onlyMessagesVisibleSinceGroupJoin($query, ?Conversation $conversation): void
    {
        if (!$conversation || $conversation->type !== 'group') {
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
