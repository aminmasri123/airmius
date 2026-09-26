<?php

namespace App\Services;

use App\Models\ClubYearPeriod;
use Carbon\CarbonInterface;

class ClubYearPeriodResolver
{
    public function idFor(int $clubId, string $type, CarbonInterface|string|null $date): ?int
    {
        if (! in_array($type, ClubYearPeriod::TYPES, true) || ! $date) {
            return null;
        }

        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr((string) $date, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }

        return ClubYearPeriod::query()
            ->where('club_id', $clubId)
            ->where('type', $type)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->value('id');
    }
}
