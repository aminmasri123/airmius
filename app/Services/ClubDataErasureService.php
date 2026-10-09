<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClubDataErasureService
{
    // These workspace records must not become personal/public records when their
    // nullable club/team/event foreign keys are cleared by the database.
    private const OWNED_TABLES = [
        'activities', 'posts', 'stories', 'files', 'folders', 'conversations',
        'training_plans', 'training_blocks', 'training_exercises', 'sport_matchings',
        'learning_courses', 'sponsor_deliverables', 'website_requests',
        'marketplace_provider_profiles', 'marketplace_warehouses',
        'marketplace_products', 'ad_campaigns', 'rides',
    ];

    private const PATH_COLUMNS = [
        'path', 'thumbnail_path', 'logo', 'logo_light', 'logo_dark', 'cover_image',
        'image', 'image_path', 'media_path', 'media_thumbnail_path', 'attachment_path',
    ];

    private array $columns = [];

    private array $children = [];

    private array $references = [];

    public function erase(Club $club): void
    {
        // The caller holds the club row lock and transaction for the entire purge.
        if (DB::transactionLevel() === 0 || ! $club->deletion_scheduled_at || $club->deletion_scheduled_at->isFuture()) {
            throw new RuntimeException('Club deletion requires an expired request and a transaction.');
        }
        $this->eraseRecords($club);
    }

    public function eraseForAdministrator(Club $club, User $actor): void
    {
        abort_unless($actor->hasRole('super_admin'), 403);
        Gate::forUser($actor)->authorize('delete', $club);
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Club deletion requires a transaction.');
        }
        $this->eraseRecords($club);
    }

    private function eraseRecords(Club $club): void
    {
        if ($blocker = app(ClubDeletionService::class)->blocker($club)) {
            throw ValidationException::withMessages(['club' => $blocker]);
        }
        $this->loadSchema();
        $teamIds = $club->teams()->lockForUpdate()->pluck('id')->all();
        $eventIds = DB::table('events')->where('club_id', $club->id)
            ->orWhere(fn ($query) => $query->whereNull('club_id')->whereIn('team_id', $teamIds))->lockForUpdate()->pluck('id')->all();
        $roots = ['clubs' => [$club->id]];
        foreach (self::OWNED_TABLES as $table) {
            if (! isset($this->columns[$table]) || ! in_array('id', $this->columns[$table], true)) {
                continue;
            }
            $roots[$table] = $this->scope($table, $club->id, $teamIds, $eventIds)->lockForUpdate()->pluck('id')->all();
        }

        // Capture cascading children before deleting anything, including uploaded
        // media in nested records. Financial records with SET NULL remain retained.
        $records = [];
        foreach ($roots as $table => $ids) {
            $this->collect($table, $ids, $records);
        }
        $paths = [];
        foreach ($records as $table => $ids) {
            $fields = array_values(array_intersect(self::PATH_COLUMNS, $this->columns[$table]));
            if ($fields) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    foreach (DB::table($table)->whereIn('id', $chunk)->get($fields) as $row) {
                        foreach ((array) $row as $field => $path) {
                            if (! is_string($path) || $path === '' || str_starts_with($path, '/')
                                || str_contains($path, '://') || in_array('..', explode('/', $path), true)) {
                                continue;
                            }
                            $disk = $table === 'training_plan_items' && $field === 'image_path' ? 'public' : UploadStorage::disk($path);
                            $paths[$disk][] = $path;
                        }
                    }
                }
            }
            $this->deleteMorphReferences($table, $ids);
        }
        $deleted = [];
        foreach (array_keys($records) as $table) {
            $this->deleteOwned($table, $records, $deleted);
        }
        app(ClubService::class)->delete($club);

        foreach ($paths as $disk => $items) {
            DB::table('club_deletion_file_cleanups')->insert([
                'disk' => $disk, 'paths' => json_encode(array_values(array_unique($items)), JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function scope(string $table, int $clubId, array $teams, array $events): Builder
    {
        $columns = $this->columns[$table];

        return DB::table($table)->where(function ($query) use ($clubId, $teams, $events, $columns) {
            $query->whereRaw('1 = 0');
            if (in_array('club_id', $columns, true)) {
                $query->orWhere('club_id', $clubId);
            }
            foreach (['team_id' => $teams, 'event_id' => $events] as $column => $ids) {
                if (in_array($column, $columns, true)) {
                    $query->orWhere(function ($nested) use ($columns, $column, $ids) {
                        if (in_array('club_id', $columns, true)) {
                            $nested->whereNull('club_id');
                        }
                        $nested->whereIn($column, $ids);
                    });
                }
            }
        });
    }

    private function loadSchema(): void
    {
        if ($this->columns !== []) {
            return;
        }
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            $this->columns[$table] = Schema::getColumnListing($table);
            foreach (Schema::getForeignKeys($table) as $key) {
                $this->references[$key['foreign_table']][] = $table;
                if (strtolower($key['on_delete']) === 'cascade' && $key['foreign_columns'] === ['id'] && count($key['columns']) === 1) {
                    $this->children[$key['foreign_table']][] = [$table, $key['columns'][0]];
                }
            }
        }
    }

    private function deleteOwned(string $table, array $records, array &$deleted): void
    {
        if (isset($deleted[$table]) || ! isset($records[$table])) {
            return;
        }
        $deleted[$table] = true;
        foreach ($this->references[$table] ?? [] as $child) {
            $this->deleteOwned($child, $records, $deleted);
        }
        if ($table !== 'clubs') {
            foreach (array_chunk($records[$table], 500) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->delete();
            }
        }
    }

    private function collect(string $table, array $ids, array &$records): void
    {
        $new = array_values(array_diff($ids, $records[$table] ?? []));
        if (! $new) {
            return;
        }
        $records[$table] = array_merge($records[$table] ?? [], $new);
        foreach ($this->children[$table] ?? [] as [$child, $column]) {
            if (! in_array('id', $this->columns[$child], true)) {
                continue;
            }
            foreach (array_chunk($new, 500) as $chunk) {
                $this->collect($child, DB::table($child)->whereIn($column, $chunk)->lockForUpdate()->pluck('id')->all(), $records);
            }
        }
    }

    private function deleteMorphReferences(string $table, array $ids): void
    {
        $class = 'App\\Models\\'.Str::studly(Str::singular($table));
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return;
        }
        $types = array_unique([$class, (new $class)->getMorphClass()]);
        foreach ([
            'likes' => 'likeable', 'moderation_flags' => 'flaggable',
            'content_reports' => 'reportable', 'activities' => 'subject',
            'gamification_xp_events' => 'subject',
        ] as $target => $morph) {
            if (! in_array($morph.'_type', $this->columns[$target] ?? [], true)) {
                continue;
            }
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table($target)->whereIn($morph.'_type', $types)->whereIn($morph.'_id', $chunk)->delete();
            }
        }
    }

    public function cleanupFiles(): void
    {
        $this->loadSchema();
        DB::table('club_deletion_file_cleanups')->orderBy('id')->chunkById(100, function ($batches) {
            foreach ($batches as $batch) {
                foreach (json_decode($batch->paths, true, flags: JSON_THROW_ON_ERROR) as $path) {
                    // A reused upload may still belong to a different club or user.
                    $referenced = false;
                    foreach ($this->columns as $table => $columns) {
                        $fields = array_intersect(self::PATH_COLUMNS, $columns);
                        if ($fields && DB::table($table)->where(function ($query) use ($fields, $path) {
                            foreach ($fields as $field) {
                                $query->orWhere($field, $path);
                            }
                        })->exists()) {
                            $referenced = true;
                            break;
                        }
                    }
                    if (! $referenced && ! Storage::disk($batch->disk)->delete($path)) {
                        throw new RuntimeException('Club upload cleanup failed.');
                    }
                }
                DB::table('club_deletion_file_cleanups')->where('id', $batch->id)->delete();
            }
        });
    }
}
