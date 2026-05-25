<?php

namespace App\Console\Commands;

use App\Models\LearningEmailDelivery;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Support\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLearningDripNotifications extends Command
{
    protected $signature = 'airmius:send-learning-drip-notifications';

    protected $description = 'Notify active learners when drip lessons become available.';

    public function handle(): int
    {
        $sent = 0;

        LearningEnrollment::query()
            ->with(['course.tutor', 'user'])
            ->where('status', 'active')
            ->whereNotNull('started_at')
            ->chunkById(100, function ($enrollments) use (&$sent) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->user || ! $enrollment->course) {
                        continue;
                    }

                    $lessons = LearningLesson::query()
                        ->where('learning_course_id', $enrollment->learning_course_id)
                        ->where('unlock_after_days', '>', 0)
                        ->get()
                        ->filter(fn (LearningLesson $lesson) => $enrollment->started_at
                            ->copy()
                            ->addDays((int) $lesson->unlock_after_days)
                            ->lte(now()));

                    foreach ($lessons as $lesson) {
                        $delivery = LearningEmailDelivery::query()->firstOrCreate(
                            [
                                'learning_enrollment_id' => $enrollment->id,
                                'learning_lesson_id' => $lesson->id,
                                'type' => 'drip_unlocked',
                            ],
                            [
                                'learning_course_id' => $enrollment->learning_course_id,
                                'user_id' => $enrollment->user_id,
                                'sent_at' => now(),
                            ],
                        );

                        if (! $delivery->wasRecentlyCreated) {
                            continue;
                        }

                        AppNotification::send($enrollment->user, 'learning.drip.unlocked', [
                            'course_id' => $enrollment->course->id,
                            'course_title' => $enrollment->course->title,
                            'lesson_id' => $lesson->id,
                            'lesson_title' => $lesson->title,
                            'url' => route('guest.learning.courses.show', $enrollment->course),
                        ]);

                        $this->sendMail($enrollment, $lesson);
                        $sent++;
                    }
                }
            });

        $this->info("Learning drip notifications sent: {$sent}");

        return self::SUCCESS;
    }

    private function sendMail(LearningEnrollment $enrollment, LearningLesson $lesson): void
    {
        if (! $enrollment->user?->email) {
            return;
        }

        try {
            Mail::raw(implode("\n\n", [
                'Hallo '.$enrollment->user->name.',',
                'eine neue Lektion ist jetzt für dich freigeschaltet:',
                $lesson->title,
                route('guest.learning.courses.show', $enrollment->course),
                'Viele Grüße',
                'Airmius',
            ]), function ($message) use ($enrollment) {
                $message->to($enrollment->user->email)
                    ->subject('Neue Lektion freigeschaltet: '.$enrollment->course->title);
            });
        } catch (Throwable) {
            // Notification delivery must not block the scheduler.
        }
    }
}
