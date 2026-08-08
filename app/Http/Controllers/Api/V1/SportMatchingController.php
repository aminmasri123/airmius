<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SportMatchingResource;
use App\Models\Sport;
use App\Models\SportMatching;
use App\Models\SportMatchingApplication;
use App\Models\SportMatchingAttendance;
use App\Models\Team;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\ChatService;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SportMatchingController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(SportMatching::MODES)],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'skill_level' => ['nullable', Rule::in(SportMatching::SKILL_LEVELS)],
            'status' => ['nullable', Rule::in(SportMatching::STATUSES)],
        ]);

        $query = SportMatching::query()
            ->with(['user:id,name,profile_photo_path', 'sport:id,name,slug', 'team.club', 'applications.user', 'applications.team', 'applications.attendance', 'attendances'])
            ->withCount([
                'applications',
                'applications as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            ])
            ->addSelect([
                'my_application' => SportMatchingApplication::query()
                    ->select('status')
                    ->whereColumn('sport_matching_id', 'sport_matchings.id')
                    ->where('user_id', $request->user()->id)
                    ->limit(1),
            ])
            ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', UserBlock::query()->select('blocked_user_id')->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', UserBlock::query()->select('user_id')->where('blocked_user_id', $request->user()->id))
            ->when($filters['mode'] ?? null, fn ($q, $value) => $q->where('mode', $value))
            ->when($filters['sport_id'] ?? null, fn ($q, $value) => $q->where('sport_id', $value))
            ->when($filters['location'] ?? $filters['city'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('city', 'like', '%'.$value.'%')
                        ->orWhere('postal_code', 'like', '%'.$value.'%')
                        ->orWhere('location_name', 'like', '%'.$value.'%');
                });
            })
            ->when($filters['radius_km'] ?? null, fn ($q, $value) => $q->where('radius_km', '<=', $value))
            ->when($filters['skill_level'] ?? null, fn ($q, $value) => $q->whereIn('skill_level', [$value, 'all']))
            ->when($filters['status'] ?? 'open', fn ($q, $value) => $q->where('status', $value))
            ->where('starts_at', '>=', now()->subHours(3))
            ->orderBy('starts_at');

        return SportMatchingResource::collection($query->paginate(min(max($request->integer('per_page', 20), 1), 50)))
            ->additional($this->catalogs($request));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['title'] = blank($data['title'] ?? null)
            ? $this->defaultTitle($data)
            : $data['title'];
        $this->validateTeam($request, $data);

        return (new SportMatchingResource(
            SportMatching::create($data)->load(['user', 'sport', 'team.club'])
        ))->response()->setStatusCode(201);
    }

    public function apply(Request $request, SportMatching $sportMatching)
    {
        abort_if(
            $sportMatching->status !== 'open' || $sportMatching->user_id === $request->user()->id,
            422,
            __('sport_matching.errors.not_open_or_own'),
        );
        $data = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($sportMatching->mode === 'team') {
            abort_if(empty($data['team_id']), 422, __('sport_matching.errors.team_required'));
            $this->assertTeamMember($request, (int) $data['team_id']);
        } else {
            $data['team_id'] = null;
        }

        $application = SportMatchingApplication::updateOrCreate(
            [
                'sport_matching_id' => $sportMatching->id,
                'user_id' => $request->user()->id,
                'team_id' => $data['team_id'] ?? null,
            ],
            ['message' => $data['message'] ?? null, 'status' => 'pending'],
        );
        AppNotification::sendLocalized(
            $sportMatching->user_id,
            'sport_matching.application',
            'sport_matching.notifications.application_title',
            'sport_matching.notifications.application_body',
            ['user' => $request->user()->name, 'matching' => $sportMatching->title],
            ['matching_id' => $sportMatching->id],
        );

        return response()->json(['data' => $application->load(['user', 'team'])]);
    }

    public function dismiss(Request $request, SportMatching $sportMatching)
    {
        abort_if(
            $sportMatching->user_id === $request->user()->id,
            422,
            __('sport_matching.errors.own_offer_cannot_be_dismissed'),
        );

        $data = $request->validate([
            'dismissed' => ['sometimes', 'boolean'],
        ]);

        if (($data['dismissed'] ?? true) === false) {
            $sportMatching->dismissals()->where('user_id', $request->user()->id)->delete();
        } else {
            $sportMatching->dismissals()->firstOrCreate(['user_id' => $request->user()->id]);
        }

        return response()->json(['data' => ['matching_id' => $sportMatching->id, 'dismissed' => ($data['dismissed'] ?? true)]]);
    }

    public function decide(Request $request, SportMatching $sportMatching, SportMatchingApplication $application)
    {
        abort_unless(
            $sportMatching->user_id === $request->user()->id
                && $application->sport_matching_id === $sportMatching->id,
            403,
            __('sport_matching.errors.owner_only'),
        );
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);
        $application->loadMissing('user');
        $previousStatus = $application->status;
        $application->update(['status' => $data['status']]);

        $conversation = null;

        if ($data['status'] === 'accepted') {
            $this->ensureAttendance($sportMatching, $request->user());
            $this->ensureAttendance($sportMatching, $application->user, $application);

            $conversation = $this->chatService->findOrCreateDirectConversation(
                $request->user(),
                $application->user,
                $sportMatching->team?->club_id,
            );

            if ($previousStatus !== 'accepted') {
                $this->chatService->sendMessage(
                    $request->user(),
                    $conversation->id,
                    __('sport_matching.chat.accepted'),
                );
            }

            $accepted = $sportMatching->applications()->where('status', 'accepted')->count();
            if ($sportMatching->mode === 'team' || $accepted >= $sportMatching->participants_needed) {
                $sportMatching->update(['status' => 'matched']);
            }
        }

        if ($previousStatus !== $data['status']) {
            $accepted = $data['status'] === 'accepted';
            AppNotification::sendLocalized(
                $application->user_id,
                'sport_matching.decision',
                $accepted
                    ? 'sport_matching.notifications.accepted_title'
                    : 'sport_matching.notifications.declined_title',
                $accepted
                    ? 'sport_matching.notifications.accepted_body'
                    : 'sport_matching.notifications.declined_body',
                ['matching' => $sportMatching->title],
                [
                    'url' => $conversation
                        ? route('auth.conversations.show', $conversation->id)
                        : route('auth.sport-matching.index'),
                    'matching_id' => $sportMatching->id,
                    'conversation_id' => $conversation?->id,
                ],
            );
        }

        return response()->json([
            'data' => $application->fresh(['user', 'team']),
            'conversation_id' => $conversation?->id,
            'conversation_url' => $conversation
                ? route('auth.conversations.show', $conversation->id)
                : null,
        ]);
    }

    public function updateAttendance(Request $request, SportMatching $sportMatching)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'cancel', 'check_in'])],
        ]);
        $attendance = $this->participantAttendance($sportMatching, $request->user());
        $action = $data['action'];

        if ($action === 'check_in') {
            abort_if($attendance->status !== 'confirmed', 422, __('sport_matching.errors.confirm_first'));
            $attendance->forceFill([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ])->save();
        } elseif ($action === 'confirm') {
            abort_if($attendance->status === 'checked_in', 422, __('sport_matching.errors.already_checked_in'));
            $attendance->forceFill([
                'status' => 'confirmed',
                'confirmed_at' => $attendance->confirmed_at ?: now(),
                'no_show_reported_at' => null,
                'no_show_reported_by' => null,
                'no_show_reason' => null,
            ])->save();
        } else {
            abort_if($attendance->status === 'checked_in', 422, __('sport_matching.errors.checked_in_cannot_cancel'));
            $attendance->forceFill(['status' => 'cancelled'])->save();
        }

        $this->notifyAttendanceParticipants($sportMatching, $request->user(), $action);

        return response()->json(['data' => $this->attendancePayload($attendance->fresh())]);
    }

    public function reportNoShow(Request $request, SportMatching $sportMatching)
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reporterAttendance = $this->participantAttendance($sportMatching, $request->user());
        abort_if($request->user()->id === (int) $data['target_user_id'], 422, __('sport_matching.errors.self_no_show'));
        abort_if($sportMatching->starts_at?->isFuture(), 422, __('sport_matching.errors.no_show_after_start'));
        abort_if(
            ! in_array($reporterAttendance->status, ['confirmed', 'checked_in'], true),
            422,
            __('sport_matching.errors.reporter_confirm_first'),
        );

        $target = $sportMatching->attendances()
            ->where('user_id', $data['target_user_id'])
            ->firstOrFail();
        abort_if($target->status === 'checked_in', 422, __('sport_matching.errors.target_checked_in'));
        abort_if($target->status === 'cancelled', 422, __('sport_matching.errors.target_cancelled'));

        $target->forceFill([
            'status' => 'no_show',
            'no_show_reported_at' => now(),
            'no_show_reported_by' => $request->user()->id,
            'no_show_reason' => $data['reason'] ?? null,
        ])->save();

        AppNotification::sendLocalized(
            $target->user_id,
            'sport_matching.no_show_reported',
            'sport_matching.notifications.no_show_title',
            'sport_matching.notifications.no_show_body',
            ['matching' => $sportMatching->title],
            [
                'url' => route('auth.sport-matching.index'),
                'matching_id' => $sportMatching->id,
            ],
        );

        return response()->json(['data' => $this->attendancePayload($target->fresh())]);
    }

    public function cancel(Request $request, SportMatching $sportMatching)
    {
        abort_unless(
            $sportMatching->user_id === $request->user()->id,
            403,
            __('sport_matching.errors.owner_only'),
        );
        $sportMatching->update(['status' => 'cancelled']);

        return response()->json(['data' => ['id' => $sportMatching->id, 'status' => 'cancelled']]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mode' => ['required', Rule::in(SportMatching::MODES)],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'title' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:240'],
            'country_code' => ['required', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['required', 'integer', 'min:1', 'max:500'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'participants_needed' => ['required', 'integer', 'min:1', 'max:500'],
            'team_size' => ['nullable', 'required_if:mode,team', 'integer', 'min:1', 'max:500'],
            'skill_level' => ['required', Rule::in(SportMatching::SKILL_LEVELS)],
        ]);
    }

    private function validateTeam(Request $request, array &$data): void
    {
        if ($data['mode'] === 'team') {
            abort_if(empty($data['team_id']), 422, __('sport_matching.errors.team_required'));
            $this->assertTeamMember($request, (int) $data['team_id']);
            $data['participants_needed'] = 1;
        } else {
            $data['team_id'] = null;
            $data['team_size'] = null;
        }
    }

    private function defaultTitle(array $data): string
    {
        $sportName = Sport::query()->whereKey($data['sport_id'])->value('name')
            ?: __('sport_matching.defaults.sport');

        return $data['mode'] === 'team'
            ? __('sport_matching.defaults.team_title', ['sport' => $sportName])
            : __('sport_matching.defaults.partner_title', ['sport' => $sportName]);
    }

    private function assertTeamMember(Request $request, int $teamId): void
    {
        abort_unless(
            Team::query()->whereKey($teamId)
                ->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id))
                ->exists(),
            403,
            __('sport_matching.errors.team_membership_required'),
        );
    }

    private function catalogs(Request $request): array
    {
        return ['meta' => [
            'sports' => Sport::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'teams' => Team::query()->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id))
                ->orderBy('name')->get(['id', 'name', 'sport_type']),
            'modes' => SportMatching::MODES,
            'skill_levels' => SportMatching::SKILL_LEVELS,
        ]];
    }

    private function ensureAttendance(
        SportMatching $sportMatching,
        User $user,
        ?SportMatchingApplication $application = null,
    ): SportMatchingAttendance {
        $attendance = SportMatchingAttendance::firstOrCreate(
            [
                'sport_matching_id' => $sportMatching->id,
                'user_id' => $user->id,
            ],
            [
                'sport_matching_application_id' => $application?->id,
                'status' => 'pending',
            ],
        );

        if ($application && ! $attendance->sport_matching_application_id) {
            $attendance->update(['sport_matching_application_id' => $application->id]);
        }

        return $attendance;
    }

    private function participantAttendance(SportMatching $sportMatching, User $user): SportMatchingAttendance
    {
        $attendance = $sportMatching->attendances()->where('user_id', $user->id)->first();
        if ($attendance) {
            return $attendance;
        }

        abort_unless(
            (int) $sportMatching->user_id === (int) $user->id
                || $sportMatching->applications()->where('user_id', $user->id)->where('status', 'accepted')->exists(),
            403,
            __('sport_matching.errors.participant_only'),
        );

        $application = $sportMatching->applications()->where('user_id', $user->id)->where('status', 'accepted')->first();

        return $this->ensureAttendance($sportMatching, $user, $application);
    }

    private function notifyAttendanceParticipants(SportMatching $sportMatching, User $actor, string $action): void
    {
        $keys = [
            'confirm' => ['attendance_confirm_title', 'attendance_confirm_body'],
            'cancel' => ['attendance_cancel_title', 'attendance_cancel_body'],
            'check_in' => ['attendance_check_in_title', 'attendance_check_in_body'],
        ];
        [$titleKey, $bodyKey] = $keys[$action];

        $sportMatching->attendances()
            ->where('user_id', '!=', $actor->id)
            ->pluck('user_id')
            ->each(fn ($recipientId) => AppNotification::sendLocalized(
                $recipientId,
                'sport_matching.attendance',
                "sport_matching.notifications.{$titleKey}",
                "sport_matching.notifications.{$bodyKey}",
                ['user' => $actor->name],
                [
                    'url' => route('auth.sport-matching.index'),
                    'matching_id' => $sportMatching->id,
                ],
            ));
    }

    private function attendancePayload(?SportMatchingAttendance $attendance): ?array
    {
        if (! $attendance) {
            return null;
        }

        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'status' => $attendance->status,
            'confirmed_at' => $attendance->confirmed_at?->toJSON(),
            'checked_in_at' => $attendance->checked_in_at?->toJSON(),
            'no_show_reported_at' => $attendance->no_show_reported_at?->toJSON(),
            'no_show_reported_by' => $attendance->no_show_reported_by,
            'no_show_reason' => $attendance->no_show_reason,
        ];
    }
}
