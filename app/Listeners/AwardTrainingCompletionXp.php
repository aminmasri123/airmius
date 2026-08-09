<?php

namespace App\Listeners;

use App\Events\DomainEventPublished;
use App\Models\SportRouteTrack;
use App\Models\TrainingLog;
use App\Services\GamificationService;
use App\Support\AppNotification;

class AwardTrainingCompletionXp
{
    public function __construct(private readonly GamificationService $gamification) {}

    public function handle(DomainEventPublished $event): void
    {
        $envelope = $event->envelope;

        if (($envelope['event_name'] ?? null) !== 'training.log.completed.v1') {
            return;
        }

        if (($envelope['aggregate']['type'] ?? null) !== (new TrainingLog)->getMorphClass()) {
            return;
        }

        $logId = filter_var(
            $envelope['aggregate']['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if (! $logId) {
            return;
        }

        $log = TrainingLog::query()
            ->with([
                'athlete:id,language,notification_channels,notification_quiet_time,trust_score,gamification_streak_days,gamification_last_active_on',
                'sportRouteTrack',
            ])
            ->find($logId);

        if (! $log || ! $this->isVerifiedCompletion($log)) {
            return;
        }

        $audienceUserIds = array_map(
            'intval',
            is_array($envelope['audience']['users'] ?? null)
                ? $envelope['audience']['users']
                : [],
        );

        if (! in_array((int) $log->user_id, $audienceUserIds, true)) {
            return;
        }

        $verification = $log->training_plan_item_id
            ? 'training_plan'
            : 'verified_gps_track';

        if (($envelope['payload']['verification'] ?? null) !== $verification) {
            return;
        }

        $xpEvent = $this->gamification->grant(
            $log->athlete,
            'training_completed',
            $log,
            ['verification' => $verification],
        );

        if (! $xpEvent || $xpEvent->amount <= 0 || $xpEvent->limited_by_daily_cap) {
            return;
        }

        AppNotification::sendLocalized(
            $log->athlete,
            'training.gamification.completed',
            'gamification.notifications.training_completed_title',
            'gamification.notifications.training_completed_body',
            ['xp' => $xpEvent->amount],
            [
                'training_log_id' => $log->id,
                'reason' => 'training_completed',
            ],
            [
                'category' => 'training',
                'priority' => 'normal',
                'dedupe_key' => 'training-completion-xp:'.$log->id,
            ],
        );
    }

    private function isVerifiedCompletion(TrainingLog $log): bool
    {
        if ($log->status !== 'completed' || ! $log->performed_at) {
            return false;
        }

        if ($log->performed_at->isAfter(now()->addMinutes(5))) {
            return false;
        }

        if ($log->training_plan_item_id) {
            return true;
        }

        $track = $log->sportRouteTrack;

        return $track instanceof SportRouteTrack
            && (int) $track->user_id === (int) $log->user_id
            && $track->status === 'completed'
            && $track->ended_at !== null;
    }
}
