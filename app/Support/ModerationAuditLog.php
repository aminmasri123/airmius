<?php

namespace App\Support;

use App\Models\ContentReport;
use App\Models\ModerationFlag;
use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ModerationAuditLog
{
    public static function record(
        ContentReport|ModerationFlag $case,
        string $action,
        ?User $actor = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $reason = null,
        array $metadata = [],
    ): ModerationLog {
        return ModerationLog::create([
            'case_type' => $case::class,
            'case_id' => $case->getKey(),
            'actor_id' => $actor?->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    public static function forCase(ContentReport|ModerationFlag $case)
    {
        return ModerationLog::query()
            ->where('case_type', $case::class)
            ->where('case_id', $case->getKey())
            ->with('actor:id,name')
            ->latest('id');
    }

    public static function contentMetadata(?Model $model): array
    {
        return [
            'content_type' => $model ? class_basename($model) : null,
            'content_id' => $model?->getKey(),
        ];
    }
}
