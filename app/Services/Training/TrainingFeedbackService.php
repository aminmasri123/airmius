<?php

namespace App\Services\Training;

use App\Models\TrainingLog;
use App\Models\TrainingLogFeedback;
use App\Models\User;
use App\Models\Activity;
use App\Support\AppNotification;
use Illuminate\Support\Facades\DB;

class TrainingFeedbackService
{
    public function __construct(
        private readonly TrainingLogAccessService $access,
        private readonly TrainingResourceService $resources,
    ) {}

    public function create(User $actor, TrainingLog $log, string $body): TrainingLogFeedback
    {
        abort_unless($this->access->canViewProtectedCaseFile($actor, $log), 403);
        abort_if($log->status === 'draft', 422, __('server.training.feedback_draft_forbidden'));

        $feedback = DB::transaction(function () use ($actor, $log, $body) {
            $feedback = $log->feedbacks()->create([
                'user_id' => $actor->id,
                'body' => trim($body),
                'role' => $this->access->feedbackRole($actor, $log),
            ]);

            $feedback->forceFill([
                'classification' => 'protected_training_record',
                'retention_until' => now()->addYears(3),
                'access_policy' => [
                    'scope' => 'training_feedback_case_file',
                    'allowed' => ['athlete', 'creator', 'assigned_trainer', 'authorized_trainer_staff', 'system_admin'],
                    'sensitive_content_in_audit' => false,
                ],
            ])->save();

            $this->audit($actor, $log, $feedback, 'training.feedback_case_file.created');

            return $feedback;
        });

        $feedback = $feedback->refresh()->load('author');

        $this->notifyRecipients($log->fresh(['feedbacks']), $feedback);

        return $feedback;
    }

    public function auditAccess(User $actor, TrainingLog $log, string $surface): void
    {
        if (! $this->access->canViewProtectedCaseFile($actor, $log) || ! $log->feedbacks()->exists()) {
            return;
        }

        $this->audit($actor, $log, null, 'training.feedback_case_file.viewed', ['surface' => $surface]);
    }

    private function audit(User $actor, TrainingLog $log, ?TrainingLogFeedback $feedback, string $type, array $extra = []): void
    {
        $team = $log->team;

        Activity::query()->create([
            'user_id' => $actor->id,
            'club_id' => $team?->club_id,
            'team_id' => $log->team_id,
            'type' => $type,
            'subject_type' => TrainingLog::class,
            'subject_id' => $log->id,
            'data' => [
                'classification' => 'protected_training_record',
                'feedback_id' => $feedback?->id,
                'feedback_role' => $feedback?->role,
                'retention_until' => $feedback?->retention_until?->toDateString(),
                'contains_sensitive_content' => false,
                ...$extra,
            ],
        ]);
    }

    private function notifyRecipients(TrainingLog $log, TrainingLogFeedback $feedback): void
    {
        $authorName = $feedback->author
            ? $this->resources->user($feedback->author)['name']
            : AppNotification::translatedReplacement(
                'server.training.notifications.fallback_someone',
                'Jemand',
            );
        $recipientIds = collect([
            $log->user_id,
            $log->created_by,
            $log->trainer_id,
        ])
            ->merge($log->feedbacks()->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $feedback->user_id);

        User::query()
            ->select(['id', 'language'])
            ->whereKey($recipientIds->all())
            ->get()
            ->each(fn (User $recipient) => AppNotification::sendLocalized(
                $recipient,
                'training.feedback',
                'server.training.notifications.feedback_title',
                'server.training.notifications.feedback_body',
                ['actor' => $authorName, 'log' => $log->title],
                [
                    'url' => route('auth.training.logs.show', $log),
                    'training_log_id' => $log->id,
                    'feedback_id' => $feedback->id,
                ],
            ));
    }
}
