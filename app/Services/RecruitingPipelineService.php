<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecruitingPipelineService
{
    public const STATUSES = [
        'new',
        'reviewing',
        'contacted',
        'interview',
        'offered',
        'hired',
        'rejected',
    ];

    private const TRANSITIONS = [
        'new' => ['new', 'reviewing', 'contacted', 'interview', 'rejected'],
        'reviewing' => ['reviewing', 'contacted', 'interview', 'rejected'],
        'contacted' => ['contacted', 'interview', 'offered', 'rejected'],
        'interview' => ['interview', 'contacted', 'offered', 'rejected'],
        'offered' => ['offered', 'interview', 'hired', 'rejected'],
        'hired' => ['hired'],
        'rejected' => ['rejected', 'reviewing'],
    ];

    public function __construct(private readonly RecruitingMatchExplanationService $matchExplanation) {}

    public function canOpen(User $user): bool
    {
        return $this->isGlobalManager($user)
            || ($user->can('club.jobs.manage') && $this->managedClubIds($user)->isNotEmpty());
    }

    /** @return array<string, mixed> */
    public function payload(User $user, array $filters, int $perPage = 25): array
    {
        $this->authorizeOpen($user);

        $filters = $this->normalizeFilters($filters);
        $base = $this->interestQuery($user)
            ->when($filters['job_id'], fn (Builder $query, int $jobId) => $query->where('organization_job_id', $jobId))
            ->when($filters['q'], fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }));

        $statusCounts = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $applications = (clone $base)
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->with([
                'job:id,club_id,sport_id,title,type,minimum_experience_level,is_published',
                'job.club:id,name',
                'job.sport:id,name,slug',
                'user:id',
                'user.sportProfiles:id,user_id,sport_id,status,experience_level',
                'user.sportProfiles.sport:id,name,slug',
                'conversation:id',
                'conversation.users:id',
                'statusChangedBy:id,name',
            ])
            ->latest('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString()
            ->through(fn (OrganizationJobInterest $interest) => $this->applicationData($interest, $user));

        $jobs = $this->jobQuery($user)
            ->with(['club:id,name', 'sport:id,name,slug'])
            ->withCount([
                'interests',
                'interests as new_interests_count' => fn (Builder $query) => $query->where('status', 'new'),
            ])
            ->latest('id')
            ->limit(100)
            ->get(['id', 'club_id', 'sport_id', 'title', 'type', 'minimum_experience_level', 'is_published'])
            ->map(fn (OrganizationJob $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'type' => $job->type,
                'is_published' => $job->is_published,
                'club' => $job->club?->only(['id', 'name']),
                'sport' => $job->sport?->only(['id', 'name', 'slug']),
                'minimum_experience_level' => $job->minimum_experience_level,
                'applications_count' => (int) $job->interests_count,
                'new_applications_count' => (int) $job->new_interests_count,
            ])
            ->values();

        return [
            'applications' => $applications,
            'jobs' => $jobs,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => $statusCounts->sum(),
                ...collect(self::STATUSES)->mapWithKeys(fn (string $status) => [
                    $status => (int) $statusCounts->get($status, 0),
                ])->all(),
            ],
            'retention_months' => 6,
        ];
    }

    public function update(User $actor, OrganizationJobInterest $interest, array $data): OrganizationJobInterest
    {
        $result = DB::transaction(function () use ($actor, $interest, $data) {
            $locked = OrganizationJobInterest::query()
                ->with('job.club')
                ->lockForUpdate()
                ->findOrFail($interest->id);
            $this->authorizeJob($actor, $locked->job);

            $previousStatus = $locked->status;
            $nextStatus = $data['status'];
            if (! in_array($nextStatus, $this->allowedTransitions($previousStatus), true)) {
                throw ValidationException::withMessages([
                    'status' => __('recruiting.validation.invalid_transition'),
                ]);
            }
            $locked->forceFill([
                'status' => $nextStatus,
                'internal_note' => $data['internal_note'] ?? null,
                'status_changed_at' => $previousStatus !== $nextStatus ? now() : $locked->status_changed_at,
                'status_changed_by' => $previousStatus !== $nextStatus ? $actor->id : $locked->status_changed_by,
                'retention_expires_at' => in_array($nextStatus, ['hired', 'rejected'], true)
                    ? now()->addMonths(6)
                    : $locked->retention_expires_at,
            ])->save();

            ClubAuditLog::record($locked->job->club, $actor, 'club.recruiting.application_updated', $locked, [
                'job_id' => $locked->organization_job_id,
                'from' => $previousStatus,
                'to' => $nextStatus,
            ]);

            return $locked->fresh(['job.club', 'job.sport', 'user', 'statusChangedBy']);
        });

        if ($result->user && $interest->status !== $result->status && in_array($result->status, ['offered', 'hired'], true)) {
            $this->notifyCandidateStatus($result);
        }

        return $result;
    }

    public function openConversation(User $actor, OrganizationJobInterest $interest): Conversation
    {
        return DB::transaction(function () use ($actor, $interest) {
            $locked = OrganizationJobInterest::query()
                ->with(['job.club', 'conversation.users:id'])
                ->lockForUpdate()
                ->findOrFail($interest->id);
            $this->authorizeJob($actor, $locked->job);
            if (! $locked->user_id || ! $locked->allow_in_app_contact || $locked->status === 'rejected') {
                throw ValidationException::withMessages([
                    'conversation' => __('recruiting.validation.chat_not_available'),
                ]);
            }

            $conversation = $locked->conversation;
            $created = false;
            if (! $conversation) {
                $conversation = Conversation::query()->create([
                    'club_id' => $locked->job->club_id,
                    'owner_id' => $actor->id,
                    'type' => 'group',
                    'name' => __('recruiting.chat.name', ['title' => $locked->job->title]),
                    'description' => __('recruiting.chat.description'),
                ]);
                $locked->forceFill(['conversation_id' => $conversation->id])->save();
                $created = true;
            }

            $conversation->users()->syncWithoutDetaching([
                $locked->user_id => ['joined_at' => now()],
                $actor->id => ['joined_at' => now()],
            ]);

            ClubAuditLog::record($locked->job->club, $actor, 'club.recruiting.chat_opened', $locked, [
                'job_id' => $locked->organization_job_id,
                'conversation_id' => $conversation->id,
                'created' => $created,
            ]);

            if ($created) {
                AppNotification::sendLocalized(
                    $locked->user_id,
                    'recruiting.chat_opened',
                    'recruiting.notifications.chat_title',
                    'recruiting.notifications.chat_body',
                    ['title' => $locked->job->title],
                    [
                        'conversation_id' => $conversation->id,
                        'url' => route('auth.conversations.index', ['conversation' => $conversation->id]),
                        'mobile_url' => 'airmius://chat/'.$conversation->id,
                    ],
                );
            }

            return $conversation->fresh(['users:id']);
        });
    }

    public function erase(User $actor, OrganizationJobInterest $interest): void
    {
        DB::transaction(function () use ($actor, $interest) {
            $locked = OrganizationJobInterest::query()
                ->with('job.club')
                ->lockForUpdate()
                ->findOrFail($interest->id);
            $this->authorizeJob($actor, $locked->job);

            ClubAuditLog::record($locked->job->club, $actor, 'club.recruiting.application_erased', $locked, [
                'job_id' => $locked->organization_job_id,
                'status' => $locked->status,
            ]);
            $locked->delete();
        });
    }

    public function authorizeJob(User $user, OrganizationJob $job): void
    {
        if ($this->isGlobalManager($user)) {
            return;
        }

        if (! $user->can('club.jobs.manage') || ! $this->managedClubIds($user)->contains($job->club_id)) {
            throw new AuthorizationException;
        }
    }

    /** @return array{status: ?string, job_id: ?int, q: string} */
    public function normalizeFilters(array $filters): array
    {
        $status = $filters['status'] ?? null;
        $jobId = filter_var($filters['job_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'status' => in_array($status, self::STATUSES, true) ? $status : null,
            'job_id' => $jobId === false ? null : $jobId,
            'q' => mb_substr(trim((string) ($filters['q'] ?? '')), 0, 120),
        ];
    }

    private function authorizeOpen(User $user): void
    {
        if (! $this->canOpen($user)) {
            throw new AuthorizationException;
        }
    }

    private function interestQuery(User $user): Builder
    {
        return OrganizationJobInterest::query()->whereHas('job', function (Builder $query) use ($user) {
            $this->applyJobScope($query, $user);
        });
    }

    private function jobQuery(User $user): Builder
    {
        $query = OrganizationJob::query();
        $this->applyJobScope($query, $user);

        return $query;
    }

    private function applyJobScope(Builder $query, User $user): void
    {
        if (! $this->isGlobalManager($user)) {
            $query->whereIn('club_id', $this->managedClubIds($user));
        }
    }

    private function managedClubIds(User $user): Collection
    {
        $owned = Club::query()->where('owner_id', $user->id)->pluck('id');
        $managed = ClubRoles::whereAny($user->clubs(), ['owner', 'admin', 'manager'])->pluck('clubs.id');

        return $owned->concat($managed)->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function isGlobalManager(User $user): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('system.manage');
    }

    /** @return array<string, mixed> */
    private function applicationData(OrganizationJobInterest $interest, User $actor): array
    {
        $actorInConversation = $interest->conversation
            ? $interest->conversation->users->contains('id', $actor->id)
            : false;

        return [
            'id' => $interest->id,
            'job_id' => $interest->organization_job_id,
            'job' => $interest->job ? [
                'id' => $interest->job->id,
                'title' => $interest->job->title,
                'type' => $interest->job->type,
                'is_published' => $interest->job->is_published,
                'club' => $interest->job->club?->only(['id', 'name']),
                'sport' => $interest->job->sport?->only(['id', 'name', 'slug']),
                'minimum_experience_level' => $interest->job->minimum_experience_level,
            ] : null,
            'name' => $interest->name,
            'email' => $interest->email,
            'phone' => $interest->phone,
            'message' => $interest->message,
            'status' => $interest->status,
            'allowed_statuses' => $this->allowedTransitions($interest->status),
            'internal_note' => $interest->internal_note,
            'profile_match' => $this->matchExplanation->forInterest($interest),
            'allow_in_app_contact' => (bool) $interest->allow_in_app_contact,
            'can_open_chat' => (bool) $interest->user_id
                && (bool) $interest->allow_in_app_contact
                && $interest->status !== 'rejected',
            'conversation_id' => $actorInConversation ? $interest->conversation_id : null,
            'membership_handoff' => in_array($interest->status, ['offered', 'hired'], true) && $interest->user_id
                ? [
                    'club_id' => $interest->job?->club_id,
                    'web_url' => route('auth.clubs.show', [
                        'club' => $interest->job?->club_id,
                        'apply_membership' => 1,
                        'recruiting_interest_id' => $interest->id,
                    ]),
                    'api_path' => '/api/v1/clubs/'.$interest->job?->club_id,
                ]
                : null,
            'submitted_at' => $interest->created_at?->toIso8601String(),
            'status_changed_at' => $interest->status_changed_at?->toIso8601String(),
            'status_changed_by' => $interest->statusChangedBy?->only(['id', 'name']),
            'retention_expires_at' => $interest->retention_expires_at?->toIso8601String(),
        ];
    }

    /** @return array<int, string> */
    private function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [$status];
    }

    private function notifyCandidateStatus(OrganizationJobInterest $interest): void
    {
        $titleKey = $interest->status === 'hired'
            ? 'recruiting.notifications.hired_title'
            : 'recruiting.notifications.offer_title';
        $bodyKey = $interest->status === 'hired'
            ? 'recruiting.notifications.hired_body'
            : 'recruiting.notifications.offer_body';

        AppNotification::sendLocalized(
            $interest->user,
            'recruiting.'.$interest->status,
            $titleKey,
            $bodyKey,
            ['title' => $interest->job->title, 'club' => $interest->job->club->name],
            [
                'job_id' => $interest->organization_job_id,
                'application_id' => $interest->id,
                'club_id' => $interest->job->club_id,
                'url' => route('auth.clubs.show', [
                    'club' => $interest->job->club_id,
                    'apply_membership' => 1,
                    'recruiting_interest_id' => $interest->id,
                ]),
                'mobile_url' => 'airmius://clubs/'.$interest->job->club_id,
            ],
        );
    }
}
