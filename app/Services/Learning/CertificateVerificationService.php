<?php

namespace App\Services\Learning;

use App\Models\LearningCertificate;

class CertificateVerificationService
{
    public function valid(LearningCertificate $certificate): bool
    {
        $certificate->loadMissing(['course', 'user', 'enrollment']);
        $enrollment = $certificate->enrollment;
        if (! $certificate->course || ! $certificate->user || ! $enrollment ||
            (int) $enrollment->learning_course_id !== (int) $certificate->learning_course_id ||
            (int) $enrollment->user_id !== (int) $certificate->user_id ||
            $enrollment->status !== 'active' || ! $enrollment->completed_at ||
            $enrollment->completed_at->isFuture() || ! $certificate->issued_at || $certificate->issued_at->isFuture() ||
            $enrollment->completed_at->gt($certificate->issued_at) ||
            (int) $enrollment->progress_percent !== 100) {
            return false;
        }

        // Check the curriculum that existed when this completion was recorded.
        // Adding a new lesson later must not invalidate an already earned credential.
        $cutoff = $enrollment->completed_at;
        $course = $certificate->course;
        $lessons = $course->lessons()->where('created_at', '<=', $cutoff)->pluck('id');
        $quizzes = $course->quizzes()->where('created_at', '<=', $cutoff)->pluck('id');
        $assignments = $course->assignments()->where('is_required', true)->where('created_at', '<=', $cutoff)->pluck('id');
        if ($lessons->count() + $quizzes->count() + $assignments->count() === 0) {
            return false;
        }
        $doneLessons = $enrollment->lessonProgress()->whereIn('learning_lesson_id', $lessons)->where('completed', true)->where('completed_at', '<=', $cutoff)->pluck('learning_lesson_id');
        $passedQuizzes = $enrollment->quizAttempts()->whereIn('learning_quiz_id', $quizzes)->where('passed', true)->where('submitted_at', '<=', $cutoff)->pluck('learning_quiz_id');
        $passedAssignments = $enrollment->assignmentSubmissions()->whereIn('learning_assignment_id', $assignments)->where('status', 'passed')->where('graded_at', '<=', $cutoff)->pluck('learning_assignment_id');

        return $lessons->diff($doneLessons)->isEmpty() && $quizzes->diff($passedQuizzes)->isEmpty() && $assignments->diff($passedAssignments)->isEmpty();
    }
}
