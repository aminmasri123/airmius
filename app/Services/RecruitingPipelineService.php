<?php

namespace App\Services;

use App\Models\Club;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                'job:id,club_id,title,type,is_published',
                'job.club:id,name',
                'statusChangedBy:id,name',
            ])
            ->latest('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString()
            ->through(fn (OrganizationJobInterest $interest) => $this->applicationData($interest));

        $jobs = $this->jobQuery($user)
            ->with('club:id,name')
            ->withCount([
                'interests',
                'interests as new_interests_count' => fn (Builder $query) => $query->where('status', 'new'),
            ])
            ->latest('id')
            ->limit(100)
            ->get(['id', 'club_id', 'title', 'type', 'is_published'])
            ->map(fn (OrganizationJob $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'type' => $job->type,
                'is_published' => $job->is_published,
                'club' => $job->club?->only(['id', 'name']),
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
        return DB::transaction(function () use ($actor, $interest, $data) {
            $locked = OrganizationJobInterest::query()
                ->with('job.club')
                ->lockForUpdate()
                ->findOrFail($interest->id);
            $this->authorizeJob($actor, $locked->job);

            $previousStatus = $locked->status;
            $nextStatus = $data['status'];
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

            return $locked->fresh(['job.club', 'statusChangedBy']);
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
    private function applicationData(OrganizationJobInterest $interest): array
    {
        return [
            'id' => $interest->id,
            'job_id' => $interest->organization_job_id,
            'job' => $interest->job ? [
                'id' => $interest->job->id,
                'title' => $interest->job->title,
                'type' => $interest->job->type,
                'is_published' => $interest->job->is_published,
                'club' => $interest->job->club?->only(['id', 'name']),
            ] : null,
            'name' => $interest->name,
            'email' => $interest->email,
            'phone' => $interest->phone,
            'message' => $interest->message,
            'status' => $interest->status,
            'internal_note' => $interest->internal_note,
            'submitted_at' => $interest->created_at?->toIso8601String(),
            'status_changed_at' => $interest->status_changed_at?->toIso8601String(),
            'status_changed_by' => $interest->statusChangedBy?->only(['id', 'name']),
            'retention_expires_at' => $interest->retention_expires_at?->toIso8601String(),
        ];
    }
}
