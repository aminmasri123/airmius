<?php

namespace App\Console\Commands;

use App\Models\ClubNumberRange;
use App\Services\ClubNumberRangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class InternalAllocateNumberRangeProbe extends Command
{
    protected $signature = 'airmius:internal-number-range-probe {range} {key}';

    protected $description = 'Internal worker for the isolated MySQL number-range concurrency probe';

    protected $hidden = true;

    public function handle(ClubNumberRangeService $service): int
    {
        $token = env('AIRMIUS_NUMBER_RANGE_PROBE_TOKEN');
        if (app()->environment('production')
            || DB::getDriverName() !== 'mysql'
            || ! is_string($token)
            || preg_match('/^[0-9a-f-]{36}$/i', $token) !== 1
        ) {
            return self::FAILURE;
        }

        $range = ClubNumberRange::query()->find($this->argument('range'));
        $key = (string) $this->argument('key');
        if (! $range || preg_match('/^[0-9a-f-]{36}$/i', $key) !== 1) {
            return self::FAILURE;
        }

        $service->allocate($range, null, $key);

        return self::SUCCESS;
    }
}
