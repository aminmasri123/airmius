<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Support\Collection;

class ApiUserPayloadLoader
{
    public function load(Collection $instances): void
    {
        $users = new Models($instances->unique('id')->values()->all());
        if ($users->isEmpty()) {
            return;
        }
        $users->loadMissing(['roles.permissions', 'permissions', 'profileBadgeAwards.badge']);
        $summaries = app(GamificationService::class)->summariesForUsers($users);
        $byId = $users->keyBy('id');

        // Preserve each instance's conversation pivot and other loaded relations.
        foreach ($instances as $instance) {
            $source = $byId[$instance->id];
            foreach (['roles', 'permissions', 'profileBadgeAwards'] as $relation) {
                $instance->setRelation($relation, $source->getRelation($relation));
            }
            $instance->setRelation('apiGamificationSummary', $summaries[$instance->id]);
        }
    }
}
