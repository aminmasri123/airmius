<?php

namespace App\Http\Controllers;

use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\ExternalFriendInvitation;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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
                        'name' => $invitation->recipient?->name ?: $invitation->email,
                        'email' => $invitation->recipient?->email ?: $invitation->email,
                    ],
                    'created_at' => $invitation->created_at,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'required_without:user_id', 'email', 'max:255'],
            'user_id' => ['nullable', 'required_without:email', 'integer', 'exists:users,id'],
        ]);

        $sender = $request->user();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $recipient = ! empty($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', $email)->first();

        if (! $recipient) {
            abort_if($email === strtolower($sender->email), 422, 'Du kannst dich nicht selbst einladen.');

            $invitation = FriendInvitation::updateOrCreate(
                [
                    'sender_id' => $sender->id,
                    'email' => $email,
                ],
                [
                    'recipient_id' => null,
                    'token' => Str::random(64),
                    'status' => 'pending',
                    'responded_at' => null,
                ]
            );

            Notification::route('mail', $email)->notify(new ExternalFriendInvitation($invitation->load('sender')));

            return back()->with('success', 'Einladung per E-Mail gesendet.');
        }

        abort_if($recipient->is($sender), 422, 'Du kannst dich nicht selbst einladen.');
        abort_unless($recipient->allowsFriendRequestsFrom($sender), 403, 'Diese Person erlaubt keine Freundschaftsanfragen von dir.');

        $alreadyFriends = Friendship::query()
            ->where('user_id', $sender->id)
            ->where('friend_id', $recipient->id)
            ->exists();

        abort_if($alreadyFriends, 422, 'Ihr seid bereits Freunde.');

        $inversePendingInvitation = FriendInvitation::query()
            ->where('sender_id', $recipient->id)
            ->where(function ($query) use ($sender) {
                $query->where('recipient_id', $sender->id)
                    ->orWhere('email', strtolower($sender->email));
            })
            ->where('status', 'pending')
            ->first();

        if ($inversePendingInvitation) {
            if (! $inversePendingInvitation->recipient_id) {
                $inversePendingInvitation->update(['recipient_id' => $sender->id]);
            }

            return $this->accept($request, $inversePendingInvitation);
        }

        $invitation = FriendInvitation::query()
            ->where('sender_id', $sender->id)
            ->where(function ($query) use ($recipient) {
                $query->where('recipient_id', $recipient->id)
                    ->orWhere('email', strtolower($recipient->email));
            })
            ->first();

        if ($invitation) {
            $invitation->update([
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'token' => Str::random(64),
                'status' => 'pending',
                'responded_at' => null,
            ]);
        } else {
            $invitation = FriendInvitation::query()->create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'token' => Str::random(64),
                'status' => 'pending',
                'responded_at' => null,
            ]);
        }

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

    public function acceptByToken(Request $request, string $token)
    {
        $invitation = FriendInvitation::query()
            ->where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        abort_unless(strtolower((string) $invitation->email) === strtolower($request->user()->email), 403);
        abort_if($invitation->sender_id === $request->user()->id, 422, 'Du kannst dich nicht selbst einladen.');

        $alreadyFriends = Friendship::query()
            ->where('user_id', $invitation->sender_id)
            ->where('friend_id', $request->user()->id)
            ->exists();

        abort_if($alreadyFriends, 422, 'Ihr seid bereits Freunde.');

        DB::transaction(function () use ($invitation, $request) {
            $invitation->update([
                'recipient_id' => $request->user()->id,
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            Friendship::firstOrCreate([
                'user_id' => $invitation->sender_id,
                'friend_id' => $request->user()->id,
            ]);

            Friendship::firstOrCreate([
                'user_id' => $request->user()->id,
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

        return redirect()->route('auth.friends.index')->with('success', 'Einladung angenommen.');
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

    public function destroy(Request $request, User $user)
    {
        $viewer = $request->user();

        abort_if($viewer->is($user), 422, 'Du kannst dich nicht selbst entfernen.');

        $deleted = Friendship::query()
            ->where(function ($query) use ($viewer, $user) {
                $query->where('user_id', $viewer->id)
                    ->where('friend_id', $user->id);
            })
            ->orWhere(function ($query) use ($viewer, $user) {
                $query->where('user_id', $user->id)
                    ->where('friend_id', $viewer->id);
            })
            ->delete();

        abort_if($deleted === 0, 422, 'Ihr seid aktuell nicht befreundet.');

        FriendInvitation::query()
            ->where(function ($query) use ($viewer, $user) {
                $query->where(function ($query) use ($viewer, $user) {
                    $query->where('sender_id', $viewer->id)
                        ->where('recipient_id', $user->id);
                })->orWhere(function ($query) use ($viewer, $user) {
                    $query->where('sender_id', $user->id)
                        ->where('recipient_id', $viewer->id);
                });
            })
            ->where('status', 'pending')
            ->update([
                'status' => 'declined',
                'responded_at' => now(),
            ]);

        return back()->with('success', 'Freundschaft wurde beendet.');
    }
}
