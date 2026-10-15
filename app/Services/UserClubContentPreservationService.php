<?php

namespace App\Services;

use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UserClubContentPreservationService
{
    public function detachBeforeAccountDeletion(User $user): void
    {
        $organizationFolderIds = $this->organizationFolderIds((int) $user->id);
        $this->queuePrivateFileCleanup($user, $organizationFolderIds);

        DB::table('files')->where('user_id', $user->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('club_id')->orWhereNotNull('team_id')->orWhereNotNull('event_id');
            })->update(['user_id' => null, 'updated_at' => now()]);

        foreach (array_chunk($organizationFolderIds, 500) as $folderIds) {
            DB::table('files')->where('user_id', $user->id)->whereIn('folder_id', $folderIds)
                ->update(['user_id' => null, 'updated_at' => now()]);
            DB::table('folders')->where('user_id', $user->id)->whereIn('id', $folderIds)
                ->update(['user_id' => null, 'updated_at' => now()]);
        }

        DB::table('folders')->where('user_id', $user->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('club_id')->orWhereNotNull('team_id')->orWhereNotNull('event_id');
            })->update(['user_id' => null, 'updated_at' => now()]);

        DB::table('organization_jobs')->where('created_by', $user->id)
            ->update(['created_by' => null, 'updated_at' => now()]);
        DB::table('club_inventory_loans')->where('borrower_id', $user->id)
            ->whereIn('status', ['active', 'return_pending', 'lost'])
            ->whereNull('borrower_name_snapshot')
            ->update(['borrower_name_snapshot' => $user->name, 'updated_at' => now()]);
        DB::table('club_inventory_loans')->where('borrower_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
        DB::table('club_inventory_loans')->where('borrower_id', $user->id)
            ->update(['borrower_id' => null, 'updated_at' => now()]);
        DB::table('club_procurement_requests')->where('requested_by', $user->id)
            ->update(['requested_by' => null, 'updated_at' => now()]);
        DB::table('club_procurement_receipts')->where('received_by', $user->id)
            ->update(['received_by' => null, 'updated_at' => now()]);

        foreach ([
            'club_service_hour_records' => ['user_id', 'recorded_by'],
            'club_service_hour_corrections' => ['user_id', 'corrected_by'],
            'club_service_hour_exemptions' => ['user_id', 'approved_by'],
            'club_governance_meeting_decision_votes' => ['user_id'],
            'club_governance_meeting_recipients' => ['user_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->where($column, $user->id)
                    ->update([$column => null, 'updated_at' => now()]);
            }
        }

        $clubPlanIds = DB::table('training_plans')->where('created_by', $user->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('team_id')->orWhereNotNull('club_training_group_id')
                    ->orWhereExists(function (Builder $assignment): void {
                        $assignment->selectRaw('1')->from('training_plan_assignments')
                            ->whereColumn('training_plan_assignments.training_plan_id', 'training_plans.id')
                            ->whereNotNull('training_plan_assignments.team_id');
                    });
            })->pluck('id');
        foreach ($clubPlanIds->chunk(500) as $ids) {
            DB::table('training_plans')->whereIn('id', $ids)
                ->update(['created_by' => null, 'updated_at' => now()]);
        }

        $clubSessionIds = DB::table('training_sessions')
            ->where(fn (Builder $query) => $query->whereNotNull('club_id')->orWhereNotNull('team_id'))
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhereExists(function (Builder $version) use ($user): void {
                        $version->selectRaw('1')->from('training_session_versions')
                            ->whereColumn('training_session_versions.training_session_id', 'training_sessions.id')
                            ->where('training_session_versions.created_by', $user->id);
                    });
            })->pluck('id');
        foreach ($clubSessionIds->chunk(500) as $ids) {
            DB::table('training_sessions')->whereIn('id', $ids)->where('created_by', $user->id)
                ->update(['created_by' => null, 'updated_at' => now()]);
            DB::table('training_session_versions')->whereIn('training_session_id', $ids)
                ->where('created_by', $user->id)
                ->update(['created_by' => null, 'updated_at' => now()]);
        }
    }

    private function queuePrivateFileCleanup(User $user, array $organizationFolderIds): void
    {
        $files = DB::table('files')->where('user_id', $user->id)
            ->whereNull('club_id')->whereNull('team_id')->whereNull('event_id')
            ->where(function (Builder $query) use ($organizationFolderIds): void {
                $query->whereNull('folder_id');
                if ($organizationFolderIds !== []) {
                    $query->orWhereNotIn('folder_id', $organizationFolderIds);
                } else {
                    $query->orWhereNotNull('folder_id');
                }
            })->pluck('path');
        $pathsByDisk = [];
        foreach ($files as $path) {
            if (! is_string($path) || $path === '' || str_starts_with($path, '/')
                || str_contains($path, '://') || in_array('..', explode('/', $path), true)) {
                continue;
            }
            $pathsByDisk[UploadStorage::disk($path)][] = $path;
        }
        foreach ($pathsByDisk as $disk => $paths) {
            DB::table('club_deletion_file_cleanups')->insert([
                'disk' => $disk,
                'paths' => json_encode(array_values(array_unique($paths)), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function organizationFolderIds(int $userId): array
    {
        $candidates = DB::table('files')->where('user_id', $userId)->whereNotNull('folder_id')
            ->pluck('folder_id')->merge(DB::table('folders')->where('user_id', $userId)->pluck('id'))
            ->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $folders = [];
        $pending = $candidates;

        while ($pending !== []) {
            $next = [];
            foreach (array_chunk($pending, 500) as $ids) {
                foreach (DB::table('folders')->whereIn('id', $ids)
                    ->get(['id', 'parent_id', 'club_id', 'team_id', 'event_id']) as $folder) {
                    $folders[(int) $folder->id] = $folder;
                    if ($folder->parent_id !== null && ! array_key_exists((int) $folder->parent_id, $folders)) {
                        $next[] = (int) $folder->parent_id;
                    }
                }
            }
            $pending = array_values(array_unique($next));
        }

        $organizationIds = [];
        foreach ($candidates as $candidate) {
            $current = $candidate;
            $visited = [];
            while (isset($folders[$current]) && ! isset($visited[$current])) {
                $visited[$current] = true;
                $folder = $folders[$current];
                if ($folder->club_id !== null || $folder->team_id !== null || $folder->event_id !== null) {
                    $organizationIds[] = $candidate;
                    break;
                }
                $current = (int) $folder->parent_id;
            }
        }

        return array_values(array_unique($organizationIds));
    }
}
