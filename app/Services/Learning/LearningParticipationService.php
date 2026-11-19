<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\CapacityBookingRuleService;
use Illuminate\Support\Facades\DB;

final class LearningParticipationService
{
    public function update(User $user, LearningEnrollment $enrollment, string $action): LearningEnrollment
    {
        abort_unless((int) $enrollment->user_id === (int) $user->id, 403);

        return DB::transaction(function () use ($user, $enrollment, $action) {
            $course = LearningCourse::whereKey($enrollment->learning_course_id)->lockForUpdate()->firstOrFail();
            $target = LearningEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $target->user_id === (int) $user->id, 403);
            if ($action === 'hide') {
                $target->hidden_at = now();
            } elseif ($action === 'show') {
                $target->hidden_at = null;
            } elseif ($action === 'leave') {
                abort_unless(in_array($target->status, ['active', 'left'], true), 422);
                $target->status = 'left';
                $target->left_at = $target->left_at ?: now();
            } elseif ($action === 'resume') {
                abort_unless(in_array($target->status, ['active', 'left'], true), 403);
                abort_unless($course->status === 'published' && $course->is_public && (! $course->published_at || $course->published_at->lte(now())), 403);
                // Reuse retained access, never create a new purchase or clear progress.
                app(CapacityBookingRuleService::class)->assertCourseEnrollmentOpen($course, $user);
                $target->status = 'active';
                $target->left_at = null;
                $target->hidden_at = null;
            }
            $target->save();

            return $target->fresh();
        });
    }
}
