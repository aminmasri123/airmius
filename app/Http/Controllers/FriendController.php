<?php

namespace App\Http\Controllers;

use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FriendController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Auth/Dashboard/Friends/Index', [
            'friends' => $user->friendships()
                ->with('friend:id,name,email,profile_photo_path')
                ->latest()
                ->get()
                ->map(fn (Friendship $friendship) => [
                    'id' => $friendship->friend->id,
                    'name' => $friendship->friend->name,
                    'email' => $friendship->friend->email,
                    'profile_photo_url' => $friendship->friend->profile_photo_url,
                    'friends_since' => $friendship->created_at,
                ]),
            'receivedInvitations' => $user->receivedFriendInvitations()
                ->where('status', 'pending')
                ->with('sender:id,name,email,profile_photo_path')
                ->latest()
                ->get()
                ->map(fn (FriendInvitation $invitation) => [
                    'id' => $invitation->id,
                    'sender' => [
                        'id' => $invitation->sender->id,
                        'name' => $invitation->sender->name,
                        'email' => $invitation->sender->email,
                        'profile_photo_url' => $invitation->sender->profile_photo_url,
                    ],
                    'created_at' => $invitation->created_at,
                ]),
            'sentInvitations' => $user->sentFriendInvitations()
                ->where('status', 'pending')
                ->with('recipient:id,name,email')
                ->latest()
                ->get()
                ->map(fn (FriendInvitation $invitation) => [
                    'id' => $invitation->id,
                    'recipient' => [
                        'name' => $invitation->recipient->name,
                        'email' => $invitation->recipient->email,
                    ],
                    'created_at' => $invitation->created_at,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'required_without:user_id', 'email', 'exists:users,email'],
            'user_id' => ['nullable', 'required_without:email', 'integer', 'exists:users,id'],
        ]);

        $sender = $request->user();
        $recipient = ! empty($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', $data['email'])->firstOrFail();

        abort_if($recipient->is($sender), 422, 'Du kannst dich nicht selbst einladen.');

        $alreadyFriends = Friendship::query()
            ->where('user_id', $sender->id)
            ->where('friend_id', $recipient->id)
            ->exists();

        abort_if($alreadyFriends, 422, 'Ihr seid bereits Freunde.');

        $inversePendingInvitation = FriendInvitation::query()
            ->where('sender_id', $recipient->id)
            ->where('recipient_id', $sender->id)
            ->where('status', 'pending')
            ->first();

        if ($inversePendingInvitation) {
            return $this->accept($request, $inversePendingInvitation);
        }

        $invitation = FriendInvitation::updateOrCreate(
            [
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
            ],
            [
                'status' => 'pending',
                'responded_at' => null,
            ]
        );

        AppNotification::send($recipient, 'friend.invite', [
            'title' => $sender->name.' möchte dich als Freund hinzufügen',
            'body' => 'Du kannst die Einladung im Freunde-Bereich annehmen oder ablehnen.',
            'url' => route('auth.friends.index'),
            'actor_id' => $sender->id,
            'actor_name' => $sender->name,
            'invitation_id' => $invitation->id,
        ]);

        return back()->with('success', 'Einladung gesendet.');
    }

    public function accept(Request $request, FriendInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        DB::transaction(function () use ($invitation) {
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            Friendship::firstOrCreate([
                'user_id' => $invitation->sender_id,
                'friend_id' => $invitation->recipient_id,
            ]);

            Friendship::firstOrCreate([
                'user_id' => $invitation->recipient_id,
                'friend_id' => $invitation->sender_id,
            ]);
        });

        AppNotification::send($invitation->sender_id, 'friend.accepted', [
            'title' => $request->user()->name.' hat deine Freundschaftsanfrage angenommen',
            'body' => 'Ihr seid jetzt verbunden.',
            'url' => route('auth.friends.index'),
            'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'invitation_id' => $invitation->id,
        ]);

        return back()->with('success', 'Einladung angenommen.');
    }

    public function decline(Request $request, FriendInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Einladung abgelehnt.');
    }
}
