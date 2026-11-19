<?php

namespace App\Services\Learning;

use App\Models\LearningEnrollment;

final class LearningContinuation
{
    public function lesson(?LearningEnrollment $enrollment): ?array
    {
        if (! $enrollment || $enrollment->status !== 'active' || ! $enrollment->course) {
            return null;
        }
        $enrollment->loadMissing(['lessonProgress', 'course.sections.lessons']);
        $completed = $enrollment->lessonProgress->where('completed', true)->pluck('learning_lesson_id')->all();
        foreach ($enrollment->course->sections->sortBy('position') as $section) {
            foreach ($section->lessons->sortBy('position') as $lesson) {
                if (! in_array($lesson->id, $completed) && ! app(LearningProgressService::class)->dripLocked($lesson, $enrollment)) {
                    return ['id' => $lesson->id, 'title' => $lesson->title];
                }
            }
        }

        return null;
    }
}
