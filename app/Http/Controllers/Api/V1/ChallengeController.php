<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Club;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Support\TeamRoles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChallengeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'upcoming', 'finished', 'cancelled'])],
            'visibility' => ['nullable', Rule::in(Challenge::VISIBILITIES)],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
        ]);

        $challenges = Challenge::query()
            ->visibleTo($request->user())
            ->with($this->relations())
            ->withCount(['participants as accepted_participants_count' => fn ($query) => $query->where('status', 'accepted'), 'comments'])
            ->when($filters['visibility'] ?? null, fn ($query, $value) => $query->where('visibility', $value))
            ->when($filters['sport_id'] ?? null, fn ($query, $value) => $query->where('sport_id', $value))
            ->when($filters['status'] ?? null, function ($query, $status) {
                match ($status) {
                    'active' => $query->where('status', 'published')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today()),
                    'upcoming' => $query->where('status', 'published')->whereDate('starts_on', '>', today()),
                    'finished' => $query->where('status', 'published')->whereDate('ends_on', '<', today()),
                    'cancelled' => $query->where('status', 'cancelled'),
                };
            })
            ->orderByRaw("CASE WHEN status = 'published' AND starts_on <= ? AND ends_on >= ? THEN 0 WHEN starts_on > ? THEN 1 ELSE 2 END", [today(), today(), today()])
            ->orderBy('starts_on')
            ->limit(100)
            ->get()
            ->map(fn (Challenge $challenge) => $this->challengeData($challenge, $request->user()));

        return response()->json([
            'data' => $challenges,
            'meta' => $this->catalogs($request->user()),
        ]);
    }

    public function show(Request $request, Challenge $challenge)
    {
        $this->assertVisible($challenge, $request->user());
        $challenge->load($this->relations())->loadCount([
            'participants as accepted_participants_count' => fn ($query) => $query->where('status', 'accepted'),
            'comments',
        ]);

        return response()->json(['data' => $this->challengeData($challenge, $request->user(), true)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:3000'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'visibility' => ['required', Rule::in(Challenge::VISIBILITIES)],
            'club_id' => ['nullable', 'required_if:visibility,club', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'required_if:visibility,team', 'integer', 'exists:teams,id'],
            'metric' => ['required', Rule::in(Challenge::METRICS)],
            'target_value' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => ['nullable', 'string', 'max:24'],
            'frequency' => ['required', Rule::in(Challenge::FREQUENCIES)],
            'checkin_slots' => ['nullable', 'array', 'min:1', 'max:4'],
            'checkin_slots.*' => ['string', 'distinct', Rule::in(['anytime', 'morning', 'midday', 'evening'])],
            'verification' => ['required', Rule::in(Challenge::VERIFICATIONS)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'invitee_ids' => ['nullable', 'array', 'max:100'],
            'invitee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $user = $request->user();
        $this->authorizeCreationScope($user, $data);
        $invitees = User::query()->whereIn('id', $data['invitee_ids'] ?? [])->get();
        foreach ($invitees as $invitee) {
            $this->authorizeInvitee($user, $invitee, $data);
        }

        $challenge = DB::transaction(function () use ($data, $user, $invitees) {
            $challenge = Challenge::create([
                ...collect($data)->except('invitee_ids')->all(),
                'creator_id' => $user->id,
                'club_id' => $data['visibility'] === 'club' ? $data['club_id'] : null,
                'team_id' => $data['visibility'] === 'team' ? $data['team_id'] : null,
                'unit' => $data['unit'] ?? $this->defaultUnit($data['metric']),
                'checkin_slots' => $data['frequency'] === 'daily'
                    ? ($data['checkin_slots'] ?? ['anytime'])
                    : ['anytime'],
                'status' => 'published',
            ]);
            $challenge->participants()->create([
                'user_id' => $user->id,
                'invited_by' => $user->id,
                'status' => 'accepted',
                'responded_at' => now(),
                'joined_at' => now(),
            ]);
            foreach ($invitees as $invitee) {
                if ($invitee->id === $user->id) {
                    continue;
                }
                $challenge->participants()->create([
                    'user_id' => $invitee->id,
                    'invited_by' => $user->id,
                    'status' => 'pending',
                ]);
            }

            return $challenge;
        });

        $invitees->where('id', '!=', $user->id)->each(fn (User $invitee) => AppNotification::sendLocalized(
            $invitee,
            'challenge.invitation',
            'challenges.notifications.invitation_title',
            'challenges.notifications.invitation_body',
            ['user' => $user->name, 'challenge' => $challenge->title],
            ['url' => route('auth.challenges.index'), 'deep_link' => 'airmius://challenges/'.$challenge->id, 'challenge_id' => $challenge->id],
        ));

        $challenge->load($this->relations())->loadCount('comments');

        return response()->json(['data' => $this->challengeData($challenge, $user, true)], 201);
    }

    public function join(Request $request, Challenge $challenge)
    {
        $this->assertVisible($challenge, $request->user());
        abort_if($challenge->status !== 'published' || $challenge->ends_on->isBefore(today()), 422, __('challenges.errors.not_joinable'));
        abort_if($challenge->visibility === 'invite_only', 403, __('challenges.errors.invitation_required'));

        $participant = $challenge->participants()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['status' => 'accepted', 'responded_at' => now(), 'joined_at' => now()],
        );

        return response()->json(['data' => $this->participantData($participant->load('user'))]);
    }

    public function respond(Request $request, Challenge $challenge)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);
        $participant = $challenge->participants()->where('user_id', $request->user()->id)->firstOrFail();
        $participant->update([
            'status' => $data['status'],
            'responded_at' => now(),
            'joined_at' => $data['status'] === 'accepted' ? now() : null,
        ]);

        if ((int) $challenge->creator_id !== (int) $request->user()->id) {
            AppNotification::sendLocalized(
                $challenge->creator_id,
                'challenge.response',
                $data['status'] === 'accepted' ? 'challenges.notifications.accepted_title' : 'challenges.notifications.declined_title',
                $data['status'] === 'accepted' ? 'challenges.notifications.accepted_body' : 'challenges.notifications.declined_body',
                ['user' => $request->user()->name, 'challenge' => $challenge->title],
                ['url' => route('auth.challenges.index'), 'deep_link' => 'airmius://challenges/'.$challenge->id, 'challenge_id' => $challenge->id],
            );
        }

        return response()->json(['data' => $this->participantData($participant->load('user'))]);
    }

    public function checkin(Request $request, Challenge $challenge, string $date)
    {
        $participant = $challenge->participants()->where('user_id', $request->user()->id)->where('status', 'accepted')->first();
        abort_unless($participant, 403, __('challenges.errors.participant_required'));
        abort_if($challenge->status !== 'published', 422, __('challenges.errors.not_active'));
        abort_if($challenge->verification === 'automatic', 422, __('challenges.errors.automatic_only'));

        try {
            $checkinDate = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => __('challenges.errors.invalid_date')]);
        }
        if ($checkinDate->format('Y-m-d') !== $date || $checkinDate->isBefore($challenge->starts_on) || $checkinDate->isAfter($challenge->ends_on) || $checkinDate->isAfter(today())) {
            throw ValidationException::withMessages(['date' => __('challenges.errors.invalid_date')]);
        }
        $checkinDate = $this->periodDate($challenge, $checkinDate);
        $data = $request->validate([
            'slot' => ['nullable', 'string', Rule::in($challenge->checkinSlots())],
            'completed' => ['sometimes', 'boolean'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! isset($data['slot']) && $challenge->checkinSlots() !== ['anytime']) {
            throw ValidationException::withMessages(['slot' => __('validation.required', ['attribute' => 'slot'])]);
        }
        $completed = (bool) ($data['completed'] ?? true);
        if (array_key_exists('value', $data) && $data['value'] !== null) {
            $completed = (float) $data['value'] >= (float) $challenge->target_value;
        }

        $slot = $data['slot'] ?? 'anytime';
        $checkin = $challenge->checkins()->updateOrCreate(
            ['user_id' => $request->user()->id, 'checkin_date' => $checkinDate->toDateString(), 'slot' => $slot],
            ['value' => $data['value'] ?? null, 'completed' => $completed, 'source' => 'manual', 'note' => $data['note'] ?? null],
        );

        return response()->json(['data' => $this->checkinData($checkin)]);
    }

    public function comments(Request $request, Challenge $challenge)
    {
        $this->assertVisible($challenge, $request->user());
        $comments = $challenge->comments()->with('user:id,name,profile_photo_path')->oldest()->limit(200)->get();

        return response()->json(['data' => $comments->map(fn ($comment) => $this->commentData($comment))]);
    }

    public function comment(Request $request, Challenge $challenge)
    {
        $this->assertVisible($challenge, $request->user());
        abort_unless($challenge->participants()->where('user_id', $request->user()->id)->where('status', 'accepted')->exists(), 403, __('challenges.errors.participant_required'));
        $data = $request->validate(['content' => ['required', 'string', 'max:1500']]);
        $comment = $challenge->comments()->create(['user_id' => $request->user()->id, 'content' => $data['content']]);
        $comment->load('user:id,name,profile_photo_path');

        $challenge->participants()->where('status', 'accepted')->where('user_id', '!=', $request->user()->id)->pluck('user_id')
            ->each(fn ($recipient) => AppNotification::sendLocalized(
                $recipient,
                'challenge.comment',
                'challenges.notifications.comment_title',
                'challenges.notifications.comment_body',
                ['user' => $request->user()->name, 'challenge' => $challenge->title],
                ['url' => route('auth.challenges.index'), 'deep_link' => 'airmius://challenges/'.$challenge->id, 'challenge_id' => $challenge->id],
            ));

        return response()->json(['data' => $this->commentData($comment)], 201);
    }

    public function cancel(Request $request, Challenge $challenge)
    {
        abort_unless($this->canCancel($request->user(), $challenge), 403);
        $challenge->update(['status' => 'cancelled']);

        return response()->json(['data' => ['id' => $challenge->id, 'status' => 'cancelled']]);
    }

    private function relations(): array
    {
        return [
            'creator:id,name,profile_photo_path', 'sport:id,name,slug', 'club:id,name', 'team:id,name,club_id',
            'participants.user:id,name,profile_photo_path',
            'checkins' => fn ($query) => $query->orderBy('checkin_date'),
        ];
    }

    private function challengeData(Challenge $challenge, User $viewer, bool $includeComments = false): array
    {
        $mine = $challenge->participants->firstWhere('user_id', $viewer->id);
        $myCheckins = $challenge->checkins->where('user_id', $viewer->id)->values();
        $slots = $challenge->checkinSlots();
        $periods = $this->periodCount($challenge) * count($slots);
        $completed = $myCheckins->where('completed', true)->count();
        $state = $challenge->status === 'cancelled' ? 'cancelled'
            : ($challenge->starts_on->isAfter(today()) ? 'upcoming' : ($challenge->ends_on->isBefore(today()) ? 'finished' : 'active'));
        $payload = [
            'id' => $challenge->id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'visibility' => $challenge->visibility,
            'metric' => $challenge->metric,
            'target_value' => $challenge->target_value,
            'unit' => $challenge->unit,
            'frequency' => $challenge->frequency,
            'checkin_slots' => $slots,
            'verification' => $challenge->verification,
            'starts_on' => $challenge->starts_on->toDateString(),
            'ends_on' => $challenge->ends_on->toDateString(),
            'status' => $challenge->status,
            'state' => $state,
            'creator' => $challenge->creator,
            'sport' => $challenge->sport,
            'club' => $challenge->club,
            'team' => $challenge->team,
            'participants' => $challenge->participants->map(fn ($participant) => $this->participantData($participant))->values(),
            'participants_count' => (int) ($challenge->accepted_participants_count ?? $challenge->participants->where('status', 'accepted')->count()),
            'comments_count' => (int) ($challenge->comments_count ?? 0),
            'my_participation' => $mine ? $this->participantData($mine) : null,
            'my_checkins' => $myCheckins->map(fn ($checkin) => $this->checkinData($checkin))->values(),
            'progress' => ['completed' => $completed, 'total' => $periods, 'percentage' => $periods > 0 ? min(100, (int) round($completed / $periods * 100)) : 0],
            'can_join' => ! $mine && $challenge->visibility !== 'invite_only' && $challenge->status === 'published' && ! $challenge->ends_on->isBefore(today()),
            'can_checkin' => $mine?->status === 'accepted' && $state === 'active' && $challenge->verification !== 'automatic',
            'can_comment' => $mine?->status === 'accepted',
            'can_cancel' => $this->canCancel($viewer, $challenge),
        ];
        if ($includeComments) {
            $payload['comments'] = $challenge->comments()->with('user:id,name,profile_photo_path')->oldest()->limit(200)->get()->map(fn ($comment) => $this->commentData($comment));
        }

        return $payload;
    }

    private function participantData(ChallengeParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'user' => $participant->user ? ['id' => $participant->user->id, 'name' => $participant->user->name, 'profile_photo_url' => $participant->user->profile_photo_url] : null,
            'status' => $participant->status,
            'joined_at' => $participant->joined_at?->toIso8601String(),
        ];
    }

    private function checkinData($checkin): array
    {
        return ['id' => $checkin->id, 'date' => $checkin->checkin_date->toDateString(), 'slot' => $checkin->slot ?: 'anytime', 'value' => $checkin->value, 'completed' => $checkin->completed, 'source' => $checkin->source, 'note' => $checkin->note];
    }

    private function commentData($comment): array
    {
        return ['id' => $comment->id, 'content' => $comment->content, 'created_at' => $comment->created_at?->toIso8601String(), 'user' => ['id' => $comment->user->id, 'name' => $comment->user->name, 'profile_photo_url' => $comment->user->profile_photo_url]];
    }

    private function assertVisible(Challenge $challenge, User $user): void
    {
        abort_unless(Challenge::query()->visibleTo($user)->whereKey($challenge->id)->exists(), 404);
    }

    private function authorizeCreationScope(User $user, array $data): void
    {
        if ($data['visibility'] === 'public') {
            abort_unless($user->hasAnyRole([...Roles::FULL_ACCESS, 'redaktor', 'support']) || $user->can('blog.publish'), 403, __('challenges.errors.public_forbidden'));
        }
        if ($data['visibility'] === 'club') {
            abort_unless($this->canManageClub($user, (int) $data['club_id']), 403, __('challenges.errors.club_forbidden'));
        }
        if ($data['visibility'] === 'team') {
            abort_unless($this->canManageTeam($user, (int) $data['team_id']), 403, __('challenges.errors.team_forbidden'));
        }
    }

    private function authorizeInvitee(User $creator, User $invitee, array $data): void
    {
        abort_if($creator->hasBlocked($invitee) || $creator->isBlockedBy($invitee), 422, __('challenges.errors.invitee_forbidden'));
        if ($data['visibility'] === 'club') {
            abort_unless($invitee->clubs()->whereKey((int) $data['club_id'])->exists(), 422, __('challenges.errors.invitee_forbidden'));
        }
        if ($data['visibility'] === 'team') {
            abort_unless($invitee->teams()->whereKey((int) $data['team_id'])->exists(), 422, __('challenges.errors.invitee_forbidden'));
        }
        if ($data['visibility'] === 'invite_only') {
            $sharedTeam = $creator->teams()->whereHas('users', fn (Builder $query) => $query->where('users.id', $invitee->id))->exists();
            $sharedClub = $creator->clubs()->whereHas('users', fn (Builder $query) => $query->where('users.id', $invitee->id))->exists();
            abort_unless($creator->isFriendsWith($invitee) || $sharedTeam || $sharedClub, 422, __('challenges.errors.invitee_forbidden'));
        }
    }

    private function canManageClub(User $user, int $clubId): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $club = Club::query()->find($clubId);

        return $club
            ? ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT)
            : false;
    }

    private function canManageTeam(User $user, int $teamId): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $team = Team::query()->find($teamId);

        return $team && (
            ClubPermissions::allowsForTeam($team, $user, ClubPermissions::EVENTS_EDIT)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::EVENTS_EDIT)
                && $team->users()
                    ->where('users.id', $user->id)
                    ->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)
                    ->exists())
        );
    }

    private function canCancel(User $user, Challenge $challenge): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        return match ($challenge->visibility) {
            'club' => $challenge->club_id && $this->canManageClub($user, (int) $challenge->club_id),
            'team' => $challenge->team_id && $this->canManageTeam($user, (int) $challenge->team_id),
            default => (int) $challenge->creator_id === (int) $user->id,
        };
    }

    private function catalogs(User $user): array
    {
        return [
            'sports' => Sport::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'friends' => $user->friendships()->with('friend:id,name,profile_photo_path')->get()->map(fn ($friendship) => ['id' => $friendship->friend->id, 'name' => $friendship->friend->name, 'profile_photo_url' => $friendship->friend->profile_photo_url])->values(),
            'clubs' => $user->clubs()->get(['clubs.id', 'clubs.name'])->map(fn ($club) => ['id' => $club->id, 'name' => $club->name, 'can_create' => $this->canManageClub($user, $club->id)])->values(),
            'teams' => $user->teams()->get(['teams.id', 'teams.name'])->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'can_create' => $this->canManageTeam($user, $team->id)])->values(),
            'metrics' => Challenge::METRICS,
            'frequencies' => Challenge::FREQUENCIES,
            'visibilities' => Challenge::VISIBILITIES,
            'can_create_public' => $user->hasAnyRole([...Roles::FULL_ACCESS, 'redaktor', 'support']) || $user->can('blog.publish'),
        ];
    }

    private function defaultUnit(string $metric): string
    {
        return match ($metric) {
            'steps' => 'Schritte', 'distance_meters' => 'm', 'duration_minutes' => 'Minuten',
            'sessions' => 'Einheiten', 'repetitions' => 'Wiederholungen', 'calories' => 'kcal', default => '',
        };
    }

    private function periodDate(Challenge $challenge, CarbonImmutable $date): CarbonImmutable
    {
        if ($challenge->frequency === 'once') {
            return CarbonImmutable::parse($challenge->starts_on);
        }
        if ($challenge->frequency === 'weekly') {
            $week = $date->startOfWeek();

            return $week->isBefore($challenge->starts_on) ? CarbonImmutable::parse($challenge->starts_on) : $week;
        }

        return $date;
    }

    private function periodCount(Challenge $challenge): int
    {
        $last = CarbonImmutable::parse($challenge->ends_on)->min(today());
        if ($last->isBefore($challenge->starts_on)) {
            return 0;
        }
        $days = CarbonImmutable::parse($challenge->starts_on)->diffInDays($last) + 1;

        return match ($challenge->frequency) {
            'once' => 1,
            'weekly' => (int) ceil($days / 7),
            default => $days,
        };
    }
}
