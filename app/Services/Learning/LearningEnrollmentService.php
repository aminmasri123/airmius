<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Support\Facades\DB;

final class LearningEnrollmentService
{
    /** @return array{enrollment: LearningEnrollment, activated: bool} */
    public function activate(User $user, LearningCourse $course, string $source = 'self', bool $notifyStudent = false): array
    {
        $result = DB::transaction(function () use ($user, $course): array {
            $enrollment = LearningEnrollment::query()->firstOrCreate(
                [
                    'learning_course_id' => $course->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'active',
                    'started_at' => now(),
                ],
            );
            $wasCreated = $enrollment->wasRecentlyCreated;
            $wasActive = $enrollment->status === 'active';

            if (! $wasActive || ! $enrollment->started_at) {
                $enrollment->forceFill([
                    'status' => 'active',
                    'started_at' => $enrollment->started_at ?: now(),
                ])->save();
            }

            return [
                'enrollment' => $enrollment->fresh(),
                'activated' => $wasCreated || ! $wasActive,
            ];
        });

        if (! $result['activated']) {
            return $result;
        }

        $enrollment = $result['enrollment'];
        $activationToken = $enrollment->updated_at?->format('Uu') ?: $enrollment->id;
        $course->loadMissing('tutor');

        if ((int) $course->user_id !== (int) $user->id && $course->tutor) {
            AppNotification::sendLocalized(
                $course->tutor,
                'learning.enrollment.created',
                'learning.notifications.enrollment_title',
                'learning.notifications.enrollment_body',
                ['student' => $user->name, 'course' => $course->title],
                [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'student_id' => $user->id,
                    'student_name' => $user->name,
                    'source' => $source,
                    'url' => route('auth.learning.studio.index', ['course' => $course->id]),
                ],
                ['dedupe_key' => 'learning-enrollment:'.$enrollment->id.':activated:'.$activationToken],
            );
        }

        if ($notifyStudent) {
            AppNotification::sendLocalized(
                $user,
                'learning.access.granted',
                'learning.notifications.access_granted_title',
                'learning.notifications.access_granted_body',
                ['course' => $course->title],
                [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'source' => $source,
                    'url' => route('guest.learning.courses.show', $course),
                ],
                ['dedupe_key' => 'learning-enrollment:'.$enrollment->id.':access:'.$activationToken],
            );
        }

        return $result;
    }
}
