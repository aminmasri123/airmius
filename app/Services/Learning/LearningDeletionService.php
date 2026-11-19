<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Models\LearningCourseSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearningDeletionService
{
    public function course(LearningCourse $course, User $actor): void
    {
        DB::transaction(function () use ($course, $actor) {
            $locked = LearningCourse::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $actor->id, 403);
            // Keep products and financial history, but prevent further sales.
            $locked->marketplaceProducts()->update(['status' => 'archived']);
            $locked->delete();
        });
    }

    public function section(LearningCourse $course, LearningCourseSection $section, User $actor): void
    {
        DB::transaction(function () use ($course, $section, $actor) {
            $locked = LearningCourse::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $actor->id, 403);
            $target = $locked->sections()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $lessons = $locked->lessons()->where('learning_course_section_id', $target->id)->pluck('id');
            // The database otherwise detaches these records instead of removing them.
            $locked->quizzes()->whereIn('learning_lesson_id', $lessons)->delete();
            $locked->assignments()->whereIn('learning_lesson_id', $lessons)->delete();
            $locked->lessons()->whereIn('id', $lessons)->delete();
            $target->delete();
            foreach ($locked->sections()->get() as $index => $remaining) {
                $remaining->update(['position' => $index + 1]);
            }
            $locked->update(['estimated_minutes' => (int) $locked->lessons()->sum('duration_minutes')]);
            $locked->enrollments()->whereNull('completed_at')->chunkById(50, function ($enrollments) use ($locked) {
                foreach ($enrollments as $enrollment) {
                    $requirements = app(LearningProgressService::class)->requirements($locked, $enrollment);
                    $total = $requirements['lessons']['total'] + $requirements['quizzes']['total'] + $requirements['assignments']['total'];
                    $done = $requirements['lessons']['completed'] + $requirements['quizzes']['completed'] + $requirements['assignments']['completed'];
                    // Removing content must not issue certificates or completion rewards.
                    $enrollment->update(['progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0]);
                }
            });
        });
    }
}
