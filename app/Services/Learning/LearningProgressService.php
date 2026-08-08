<?php

namespace App\Services\Learning;

use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningLessonProgress;
use App\Models\LearningQuiz;
use App\Models\LearningQuizAttempt;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LearningProgressService
{
    public function dripLocked(LearningLesson $lesson, ?LearningEnrollment $enrollment): bool
    {
        return $enrollment?->started_at
            && (int) $lesson->unlock_after_days > 0
            && now()->lt($this->dripAvailableAt($lesson, $enrollment));
    }

    public function dripAvailableAt(LearningLesson $lesson, ?LearningEnrollment $enrollment)
    {
        if (! $enrollment?->started_at || (int) $lesson->unlock_after_days <= 0) {
            return null;
        }

        return $enrollment->started_at->copy()->addDays((int) $lesson->unlock_after_days);
    }

    public function completeLesson(LearningCourse $course, LearningEnrollment $enrollment, LearningLesson $lesson): LearningLessonProgress
    {
        $progress = LearningLessonProgress::query()->firstOrNew([
            'learning_enrollment_id' => $enrollment->id,
            'learning_lesson_id' => $lesson->id,
        ]);
        $progress->completed = true;
        $progress->completed_at ??= now();
        $progress->save();

        $this->refreshCompletion($course, $enrollment);

        return $progress;
    }

    public function trackLesson(
        LearningCourse $course,
        LearningEnrollment $enrollment,
        LearningLesson $lesson,
        int $watchSeconds,
    ): LearningLessonProgress {
        $progress = LearningLessonProgress::query()->firstOrNew([
            'learning_enrollment_id' => $enrollment->id,
            'learning_lesson_id' => $lesson->id,
        ]);
        $progress->watch_seconds = max((int) $progress->watch_seconds, max(0, $watchSeconds));
        $duration = max(0, (int) $lesson->duration_minutes * 60);

        if ($duration > 0 && $progress->watch_seconds >= (int) ceil($duration * 0.8)) {
            $progress->completed = true;
            $progress->completed_at ??= now();
        }

        $progress->save();
        $this->refreshCompletion($course, $enrollment);

        return $progress;
    }

    /** @param array<string, mixed> $answers */
    public function submitQuiz(
        LearningCourse $course,
        LearningEnrollment $enrollment,
        LearningQuiz $quiz,
        User $user,
        array $answers,
    ): LearningQuizAttempt {
        $quiz->loadMissing('questions');
        $normalizedAnswers = collect($answers)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->normalizeAnswer($value)])
            ->all();
        $correctIds = $quiz->questions
            ->filter(fn ($question) => $this->normalizeAnswer($question->correct_options ?: [])
                === ($normalizedAnswers[(string) $question->id] ?? []))
            ->pluck('id')
            ->all();
        $score = (int) round((count($correctIds) / max(1, $quiz->questions->count())) * 100);

        $attempt = LearningQuizAttempt::query()->create([
            'learning_quiz_id' => $quiz->id,
            'learning_enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'answers' => $normalizedAnswers,
            'correct_question_ids' => $correctIds,
            'score_percent' => $score,
            'passed' => $score >= (int) $quiz->pass_percent,
            'submitted_at' => now(),
        ]);

        $this->refreshCompletion($course, $enrollment);

        return $attempt;
    }

    /**
     * @return array{
     *   enrollment: LearningEnrollment,
     *   certificate: LearningCertificate|null,
     *   certificate_created: bool,
     *   newly_completed: bool,
     *   requirements: array<string, mixed>
     * }
     */
    public function refreshCompletion(LearningCourse $course, LearningEnrollment $enrollment): array
    {
        $result = DB::transaction(function () use ($course, $enrollment): array {
            $locked = LearningEnrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $requirements = $this->requirements($course, $locked);
            $wasCompleted = (bool) $locked->completed_at || $locked->certificate()->exists();
            $newlyCompleted = ! $wasCompleted && $requirements['complete'];
            $completed = $wasCompleted || $requirements['complete'];
            $totalMilestones = (int) ($requirements['lessons']['total'] + $requirements['quizzes']['total'] + $requirements['assignments']['total']);
            $completedMilestones = (int) ($requirements['lessons']['completed'] + $requirements['quizzes']['completed'] + $requirements['assignments']['completed']);
            $progressPercent = $completed
                ? 100
                : min(99, (int) round(($completedMilestones / max(1, $totalMilestones)) * 100));

            $locked->forceFill([
                'progress_percent' => $progressPercent,
                'completed_at' => $completed ? ($locked->completed_at ?: now()) : null,
            ])->save();

            $certificate = null;
            $certificateCreated = false;
            if ($completed) {
                $certificate = $locked->certificate()->firstOrCreate([], [
                    'learning_course_id' => $course->id,
                    'user_id' => $locked->user_id,
                    'code' => 'AIR-LEARN-'.Str::upper(Str::random(10)),
                    'issued_at' => now(),
                ]);
                $certificateCreated = $certificate->wasRecentlyCreated;
            }

            return [
                'enrollment' => $locked,
                'certificate' => $certificate,
                'certificate_created' => $certificateCreated,
                'newly_completed' => $newlyCompleted,
                'requirements' => $requirements,
            ];
        });

        if ($result['certificate_created']) {
            $this->notifyCompletion($course, $result['enrollment'], $result['certificate']);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function requirements(LearningCourse $course, LearningEnrollment $enrollment): array
    {
        $totalLessons = $course->lessons()->count();
        $completedLessons = LearningLessonProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('completed', true)
            ->whereHas('lesson', fn ($query) => $query->where('learning_course_id', $course->id))
            ->count();
        $quizIds = $course->quizzes()->pluck('id');
        $passedQuizIds = $quizIds->isEmpty()
            ? collect()
            : LearningQuizAttempt::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('passed', true)
                ->whereIn('learning_quiz_id', $quizIds)
                ->distinct()
                ->pluck('learning_quiz_id');
        $assignmentIds = $course->assignments()->where('is_required', true)->pluck('id');
        $passedAssignmentIds = $assignmentIds->isEmpty()
            ? collect()
            : LearningAssignmentSubmission::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('status', 'passed')
                ->whereIn('learning_assignment_id', $assignmentIds)
                ->distinct()
                ->pluck('learning_assignment_id');
        $milestoneCount = $totalLessons + $quizIds->count() + $assignmentIds->count();

        return [
            'lessons' => [
                'label' => __('learning.completion.lessons'),
                'total' => (int) $totalLessons,
                'completed' => (int) $completedLessons,
            ],
            'quizzes' => [
                'label' => __('learning.completion.quizzes'),
                'total' => $quizIds->count(),
                'completed' => $passedQuizIds->count(),
            ],
            'assignments' => [
                'label' => __('learning.completion.assignments'),
                'total' => $assignmentIds->count(),
                'completed' => $passedAssignmentIds->count(),
            ],
            'complete' => $milestoneCount > 0
                && $completedLessons >= $totalLessons
                && $quizIds->diff($passedQuizIds)->isEmpty()
                && $assignmentIds->diff($passedAssignmentIds)->isEmpty(),
        ];
    }

    private function notifyCompletion(LearningCourse $course, LearningEnrollment $enrollment, LearningCertificate $certificate): void
    {
        $course->loadMissing('tutor');
        $enrollment->loadMissing('user');
        $student = $enrollment->user;

        if ($student) {
            AppNotification::sendLocalized(
                $student,
                'learning.certificate.issued',
                'learning.notifications.certificate_issued_title',
                'learning.notifications.certificate_issued_body',
                ['course' => $course->title],
                [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'certificate_code' => $certificate->code,
                    'url' => route('guest.learning.courses.show', $course),
                ],
                ['dedupe_key' => 'learning-certificate:'.$certificate->id.':issued'],
            );
        }

        if ($course->tutor && (int) $course->user_id !== (int) $enrollment->user_id) {
            AppNotification::sendLocalized(
                $course->tutor,
                'learning.student.completed',
                'learning.notifications.student_completed_title',
                'learning.notifications.student_completed_body',
                [
                    'student' => $student?->name ?: AppNotification::translatedReplacement(
                        'server.training.notifications.fallback_athlete',
                        'Athlete',
                    ),
                    'course' => $course->title,
                ],
                [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'student_id' => $enrollment->user_id,
                    'certificate_code' => $certificate->code,
                    'url' => route('auth.learning.studio.index', ['course' => $course->id]),
                ],
                ['dedupe_key' => 'learning-certificate:'.$certificate->id.':tutor-completion'],
            );
        }
    }

    /** @return list<string> */
    private function normalizeAnswer(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($item) => Str::lower(trim((string) $item)))
            ->filter()
            ->sort()
            ->values()
            ->all();
    }
}
