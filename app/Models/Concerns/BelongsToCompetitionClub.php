<?php

namespace App\Models\Concerns;

use App\Models\Competition;
use Illuminate\Validation\ValidationException;

trait BelongsToCompetitionClub
{
    protected static function bootBelongsToCompetitionClub(): void
    {
        static::saving(function ($model): void {
            if (! $model->competition_id) {
                return;
            }

            $clubId = Competition::query()->whereKey($model->competition_id)->value('club_id');

            if (! $clubId) {
                return;
            }

            if (! $model->club_id) {
                $model->club_id = $clubId;

                return;
            }

            if ((int) $model->club_id !== (int) $clubId) {
                throw ValidationException::withMessages([
                    'club_id' => __('validation.exists', ['attribute' => 'club_id']),
                ]);
            }
        });
    }
}
