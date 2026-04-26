<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
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

        return $this->renderIndex($selected);
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
            'type' => ['required', 'in:direct,group'],
            'club_id' => ['nullable', 'exists:clubs,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        $participantIds = collect($data['participant_ids'])
            ->push(auth()->id())
            ->unique()
            ->values();

        abort_if($data['type'] === 'direct' && $participantIds->count() !== 2, 422);
        abort_if($data['type'] === 'group' && $participantIds->count() < 3, 422);

        if ($data['type'] === 'direct') {
            $conversation = Conversation::query()
                ->where('type', 'direct')
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->whereHas('users', fn ($query) => $query->where('users.id', $participantIds->first(fn ($id) => $id !== auth()->id())))
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'club_id' => $data['club_id'] ?? null,
                ]);
                $conversation->users()->attach($participantIds);
            }
        } else {
            $conversation = Conversation::create([
                'type' => 'group',
                'club_id' => $data['club_id'] ?? null,
            ]);
            $conversation->users()->attach($participantIds);
        }

        if (!empty($data['message'])) {
            $conversation->messages()->create([
                'sender_id' => auth()->id(),
                'message' => $data['message'],
            ]);
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

        return $this->renderIndex($conversation);
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

    private function renderIndex(?Conversation $selectedConversation = null)
    {
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
            ->with(['users:id,name'])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        $selectedConversation ??= $conversations->first();

        if ($selectedConversation) {
            MessageReceipt::query()
                ->where('user_id', auth()->id())
                ->whereNull('read_at')
                ->whereHas('message', fn ($query) => $query->where('conversation_id', $selectedConversation->id))
                ->update([
                    'delivered_at' => now(),
                    'read_at' => now(),
                ]);
        }

        $selectedConversation?->load([
            'users:id,name',
            'messages' => fn ($query) => $query
                ->with(['sender:id,name', 'receipts:id,message_id,user_id,delivered_at,read_at'])
                ->oldest('id')
                ->limit(80),
        ]);

        return Inertia::render('Auth/Dashboard/Chat/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'users' => User::query()
                ->whereKeyNot(auth()->id())
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
        ]);
    }
}
