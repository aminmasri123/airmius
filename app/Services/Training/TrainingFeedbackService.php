<?php

namespace App\Services\Training;

use App\Models\TrainingLog;
use App\Models\TrainingLogFeedback;
use App\Models\User;
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
        abort_unless($this->access->canView($actor, $log), 403);
        abort_if($log->status === 'draft', 422, __('server.training.feedback_draft_forbidden'));

        $feedback = DB::transaction(fn () => $log->feedbacks()->create([
            'user_id' => $actor->id,
            'body' => trim($body),
            'role' => $this->access->feedbackRole($actor, $log),
        ]));

        $this->notifyRecipients($log->fresh(['feedbacks']), $feedback->load('author'));

        return $feedback;
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
