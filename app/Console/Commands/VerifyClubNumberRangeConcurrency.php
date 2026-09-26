<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubNumberAllocation;
use App\Models\ClubNumberRange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

final class VerifyClubNumberRangeConcurrency extends Command
{
    protected $signature = 'airmius:verify-club-number-range-concurrency
        {--club= : Existing club ID in the isolated MySQL copy}
        {--confirm-isolated : Confirm that this is a disposable non-production database copy}
        {--json : Print machine-readable result without identifiers}';

    protected $description = 'Verify two concurrent number allocations through separate MySQL connections and remove probe data';

    public function handle(): int
    {
        $report = $this->runProbe();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Check', 'Status'], collect($report['checks'])->map(fn ($status, $check) => [
                $check, strtoupper($status),
            ])->all());
        }

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function runProbe(): array
    {
        $checks = [
            'isolated_confirmation' => 'fail',
            'mysql_connection' => 'pending',
            'parallel_workers' => 'pending',
            'unique_sequences' => 'pending',
            'cleanup' => 'pending',
        ];
        if (! $this->option('confirm-isolated') || app()->environment('production')) {
            return $this->report($checks);
        }
        $checks['isolated_confirmation'] = 'pass';
        try {
            if (DB::getDriverName() !== 'mysql') {
                $checks['mysql_connection'] = 'fail';

                return $this->report($checks);
            }
            DB::select('SELECT 1');
            $checks['mysql_connection'] = 'pass';
        } catch (Throwable) {
            $checks['mysql_connection'] = 'fail';

            return $this->report($checks);
        }

        $clubId = filter_var($this->option('club'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $club = $clubId ? Club::query()->find($clubId) : null;
        if (! $club) {
            $checks['parallel_workers'] = 'fail';

            return $this->report($checks);
        }

        $range = null;
        $allocationIds = [];
        try {
            $probe = Str::lower(Str::random(12));
            $start = 700000000;
            $range = ClubNumberRange::query()->create([
                'club_id' => $club->id,
                'scope' => 'member',
                'name' => 'Concurrency probe '.$probe,
                'prefix' => 'PROBE-'.$probe.'-',
                'suffix' => '',
                'padding' => 9,
                'start_number' => $start,
                'next_number' => $start,
                'reset_policy' => 'never',
                'is_active' => true,
            ]);
            $token = Str::uuid()->toString();
            $workers = collect([Str::uuid()->toString(), Str::uuid()->toString()])
                ->map(function (string $key) use ($range, $token) {
                    $process = new Process(
                        [PHP_BINARY, base_path('artisan'), 'airmius:internal-number-range-probe', (string) $range->id, $key],
                        base_path(),
                        ['AIRMIUS_NUMBER_RANGE_PROBE_TOKEN' => $token],
                        null,
                        30,
                    );
                    $process->disableOutput();
                    $process->start();

                    return $process;
                });
            $workers->each(fn (Process $process) => $process->wait());
            $checks['parallel_workers'] = $workers->every(fn (Process $process) => $process->isSuccessful())
                ? 'pass' : 'fail';

            $allocations = ClubNumberAllocation::query()
                ->where('club_number_range_id', $range->id)
                ->orderBy('sequence_number')
                ->get();
            $allocationIds = $allocations->pluck('id')->all();
            $range->refresh();
            $checks['unique_sequences'] = $checks['parallel_workers'] === 'pass'
                && $allocations->pluck('sequence_number')->all() === [$start, $start + 1]
                && $allocations->pluck('formatted_number')->unique()->count() === 2
                && $range->next_number === $start + 2
                ? 'pass' : 'fail';
        } catch (Throwable) {
            $checks['parallel_workers'] = 'fail';
            $checks['unique_sequences'] = 'fail';
        } finally {
            try {
                DB::transaction(function () use ($range, &$allocationIds) {
                    if ($range) {
                        $persistedIds = ClubNumberAllocation::query()
                            ->where('club_number_range_id', $range->id)
                            ->pluck('id')
                            ->all();
                        $allocationIds = array_values(array_unique([...$allocationIds, ...$persistedIds]));
                    }
                    if ($allocationIds !== []) {
                        Activity::query()
                            ->where('subject_type', ClubNumberAllocation::class)
                            ->whereIn('subject_id', $allocationIds)
                            ->delete();
                    }
                    if ($range) {
                        ClubNumberAllocation::query()->where('club_number_range_id', $range->id)->delete();
                        $range->delete();
                    }
                });
                $checks['cleanup'] = $range === null || (
                    ! ClubNumberRange::query()->whereKey($range->id)->exists()
                    && ! ClubNumberAllocation::query()->where('club_number_range_id', $range->id)->exists()
                    && ($allocationIds === [] || ! Activity::query()
                        ->where('subject_type', ClubNumberAllocation::class)
                        ->whereIn('subject_id', $allocationIds)
                        ->exists())
                ) ? 'pass' : 'fail';
            } catch (Throwable) {
                $checks['cleanup'] = 'fail';
            }
        }

        return $this->report($checks);
    }

    private function report(array $checks): array
    {
        return [
            'contract' => 'club-number-range-mysql-concurrency.v1',
            'passed' => collect($checks)->every(fn ($status) => $status === 'pass'),
            'checks' => $checks,
        ];
    }
}
