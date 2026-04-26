<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Services\ChatService;
use App\Support\AppNotification;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private ChatService $service) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);

        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);

        $message = $this->service->sendMessage(
            auth()->user(),
            $data['conversation_id'],
            $data['message']
        );

        $conversation->users()
            ->where('users.id', '!=', auth()->id())
            ->get()
            ->each(fn ($recipient) => AppNotification::send($recipient, 'chat.message', [
                'title' => 'Neue Nachricht von '.auth()->user()->name,
                'body' => str($message->message)->limit(120)->toString(),
                'url' => route('auth.conversations.index', ['conversation' => $conversation->id]),
                'actor_id' => auth()->id(),
                'actor_name' => auth()->user()->name,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message->load('sender'),
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

        return response()->json(['success' => true]);
    }
}
