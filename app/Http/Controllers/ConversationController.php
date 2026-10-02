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
use Illuminate\Validation\Rule;
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
            $conversation->users()->updateExistingPivot(auth()->id(), [
                'role' => Conversation::ROLE_OWNER,
            ]);
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
        abort_unless($conversation->canManageMembers((int) auth()->id()), 403);

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
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedConversation->loadCount('users');
            $remainingAfterLeave = max(0, $lockedConversation->users_count - 1);

            if ($remainingAfterLeave === 0 || (($data['delete_conversation'] ?? false) && $remainingAfterLeave <= 1)) {
                $lockedConversation->delete();

                return;
            }

            $remainingUserIds = $lockedConversation->users()
                ->where('users.id', '!=', $request->user()->id)
                ->pluck('users.id');

            $this->addSystemMessage(
                $lockedConversation,
                $request->user(),
                $request->user()->name.' hat die Gruppe verlassen.',
                'group.member.left',
                ['user_id' => $request->user()->id]
            );

            if ($lockedConversation->isOwner((int) $request->user()->id)
                && (int) $lockedConversation->owner_id === (int) $request->user()->id) {
                $otherOwnerId = $lockedConversation->users()
                    ->where('users.id', '!=', $request->user()->id)
                    ->wherePivot('role', Conversation::ROLE_OWNER)
                    ->value('users.id');
                $nextOwnerId = $otherOwnerId ?: $remainingUserIds->first();

                if (! $otherOwnerId && $nextOwnerId) {
                    $lockedConversation->users()->updateExistingPivot($nextOwnerId, [
                        'role' => Conversation::ROLE_OWNER,
                    ]);
                }

                if ((int) $lockedConversation->owner_id === (int) $request->user()->id) {
                    $lockedConversation->update(['owner_id' => $nextOwnerId]);
                }
            }

            $lockedConversation->users()->detach(auth()->id());
        });

        broadcast(new ChatConversationUpdated($conversation, 'member.left', $broadcastUserIds))->toOthers();

        return redirect()
            ->route('auth.conversations.index')
            ->with('success', __('server.chat.left'));
    }

    public function clear(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', $request->user()->id)->exists(), 403);
        abort_if($conversation->type !== 'direct', 422, __('server.chat.clear_direct_only'));

        DB::transaction(function () use ($conversation, $request) {
            $clearedAt = now();

            $conversation->users()->updateExistingPivot($request->user()->id, [
                'cleared_at' => $clearedAt,
                'cleared_message_id' => $conversation->messages()->max('id') ?? 0,
            ]);

            MessageReceipt::query()
                ->where('user_id', $request->user()->id)
                ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))
                ->whereNull('read_at')
                ->update([
                    'delivered_at' => $clearedAt,
                    'read_at' => $clearedAt,
                ]);

            $request->user()->appNotifications()
                ->where('type', 'chat.message')
                ->where('data->conversation_id', $conversation->id)
                ->where('read', false)
                ->update(['read' => true]);
        });

        return redirect()
            ->route('auth.conversations.index')
            ->with('success', __('server.chat.cleared'));
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
            'posting_policy' => ['nullable', Rule::in(Conversation::POSTING_POLICIES)],
        ]);

        $conversation->update(array_filter([
            'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'posting_policy' => $data['posting_policy'] ?? null,
        ], fn ($value, $key) => $key !== 'posting_policy' || $value !== null, ARRAY_FILTER_USE_BOTH));

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
    public function destroy(Request $request, Conversation $conversation)
    {
        abort_if($conversation->type !== 'group', 422, __('server.chat.delete_group_only'));
        abort_unless($conversation->isOwner((int) $request->user()->id), 403);

        $recipientIds = $conversation->users()->pluck('users.id')->all();
        $conversation->delete();

        broadcast(new ChatConversationUpdated($conversation, 'group.deleted', $recipientIds))->toOthers();

        return redirect()
            ->route('auth.conversations.index')
            ->with('success', __('server.chat.group_deleted'));
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
        abort_unless($conversation->canManageMembers((int) auth()->id()), 403);
        abort_if($user->id === auth()->id(), 422, __('server.chat.remove_self'));
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 404);

        $actorRole = $conversation->roleFor((int) auth()->id());
        $targetRole = $conversation->roleFor((int) $user->id);
        abort_if(
            $actorRole === Conversation::ROLE_MODERATOR && $targetRole !== Conversation::ROLE_MEMBER,
            403,
            __('server.chat.moderator_member_only')
        );
        abort_if(
            $targetRole === Conversation::ROLE_OWNER
                && $conversation->ownerCount() <= 1,
            422,
            __('server.chat.last_owner_required')
        );

        if ((int) $conversation->owner_id === (int) $user->id) {
            $conversation->update([
                'owner_id' => $conversation->users()
                    ->where('users.id', '!=', $user->id)
                    ->wherePivot('role', Conversation::ROLE_OWNER)
                    ->value('users.id'),
            ]);
        }

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
        $conversation->users()->updateExistingPivot((int) $data['user_id'], [
            'role' => Conversation::ROLE_OWNER,
        ]);
        $conversation->users()->updateExistingPivot((int) $request->user()->id, [
            'role' => Conversation::ROLE_MEMBER,
        ]);
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

    public function updateMemberRole(Request $request, Conversation $conversation, User $user)
    {
        abort_if($conversation->type !== 'group', 422, __('server.chat.members_group_only'));
        abort_unless($conversation->isOwner((int) $request->user()->id), 403);
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(Conversation::GROUP_ROLES)],
        ]);
        $currentRole = $conversation->roleFor((int) $user->id);

        abort_if(
            $currentRole === Conversation::ROLE_OWNER
                && $data['role'] !== Conversation::ROLE_OWNER
                && $conversation->ownerCount() <= 1,
            422,
            __('server.chat.last_owner_required')
        );

        $conversation->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        if ($data['role'] === Conversation::ROLE_OWNER && ! $conversation->owner_id) {
            $conversation->update(['owner_id' => $user->id]);
        } elseif ((int) $conversation->owner_id === (int) $user->id && $data['role'] !== Conversation::ROLE_OWNER) {
            $conversation->update([
                'owner_id' => $conversation->users()
                    ->where('users.id', '!=', $user->id)
                    ->wherePivot('role', Conversation::ROLE_OWNER)
                    ->value('users.id'),
            ]);
        }

        $roleLabel = __('server.chat.roles.'.$data['role']);
        $this->addSystemMessage(
            $conversation,
            $request->user(),
            $request->user()->name.' hat '.$user->name.' die Rolle '.$roleLabel.' gegeben.',
            'group.member.role_updated',
            ['user_id' => $user->id, 'role' => $data['role']]
        );

        broadcast(new ChatConversationUpdated($conversation, 'member.role_updated'))->toOthers();

        return back()->with('success', __('server.chat.role_updated'));
    }

    private function renderIndex(?Conversation $selectedConversation = null, ?Request $request = null)
    {
        $request ??= request();
        $messageLimit = min(500, max(50, (int) $request->integer('message_limit', 50)));
        $messageSearch = trim((string) $request->query('message_search', ''));
        $userConversationIds = Conversation::query()
            ->visibleToUser((int) auth()->id())
            ->pluck('id');

        MessageReceipt::query()
            ->where('user_id', auth()->id())
            ->whereNull('delivered_at')
            ->whereHas('message', fn ($query) => $query
                ->whereIn('conversation_id', $userConversationIds)
                ->visibleSinceGroupJoin((int) auth()->id()))
            ->update(['delivered_at' => now()]);

        $conversations = Conversation::query()
            ->visibleToUser((int) auth()->id())
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
            'directPeerHasBlocked' => $selectedConversation?->type === 'direct'
                ? (bool) $request->user()->hasBlocked(
                    $selectedConversation->users
                        ->first(fn (User $user) => (int) $user->id !== (int) auth()->id())
                )
                : false,
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
        return $conversation->isOwner((int) auth()->id());
    }

    private function onlyMessagesVisibleSinceGroupJoin($query, ?Conversation $conversation): void
    {
        if (! $conversation) {
            return;
        }

        $membership = DB::table('conversation_users')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', auth()->id())
            ->first(['joined_at', 'cleared_at', 'cleared_message_id']);

        if ($conversation->type === 'group' && $membership?->joined_at) {
            $query->where('created_at', '>=', $membership->joined_at);
        }

        if ($membership?->cleared_message_id !== null) {
            $query->where('id', '>', $membership->cleared_message_id);
        }
    }
}
