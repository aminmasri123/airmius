<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRoleApplication;
use App\Notifications\TrainerRegistrationReviewRequested;
use App\Support\AppNotification;
use Illuminate\Support\Facades\Notification;

class AccountRoleApplicationService
{
    /**
     * Activate the trainer workspace immediately and keep the request pending
     * for the Airmius review process.
     *
     * @return array{application: UserRoleApplication, created: bool}
     */
    public function submitTrainer(User $user, ?string $message = null, ?bool $roleWasAlreadyActive = null): array
    {
        $pending = $user->roleApplications()
            ->where('type', UserRoleApplication::TYPE_TRAINER)
            ->where('status', UserRoleApplication::STATUS_PENDING)
            ->latest('id')
            ->first();

        if ($pending) {
            return ['application' => $pending, 'created' => false];
        }

        $roleWasAlreadyActive ??= $user->hasRole('coach');

        $application = $user->roleApplications()->create([
            'type' => UserRoleApplication::TYPE_TRAINER,
            'status' => UserRoleApplication::STATUS_PENDING,
            'message' => $message,
            'role_activated' => ! $roleWasAlreadyActive,
            'requested_at' => now(),
        ]);

        if (! $roleWasAlreadyActive) {
            $user->assignRole('coach');
        }

        AppNotification::send($user, 'role.application_submitted', [
            'title' => 'Trainerfunktion aktiviert',
            'body' => 'Dein Trainerzugang ist sofort aktiv. Airmius prüft deinen Antrag und informiert dich über das Ergebnis.',
            'url' => route('auth.trainer-cockpit.index'),
            'application_id' => $application->id,
            'application_type' => $application->type,
            'application_status' => $application->status,
        ]);

        $reviewers = User::permission('system.manage')->get();
        $reviewers->each(function (User $reviewer) use ($application, $user) {
            AppNotification::send($reviewer, 'role.application_review_requested', [
                'title' => 'Neuer Trainerantrag',
                'body' => $user->name.' hat einen Trainerantrag gestellt.',
                'url' => route('admin.trainer-applications.index'),
                'application_id' => $application->id,
                'application_type' => $application->type,
                'application_status' => $application->status,
            ]);
        });

        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new TrainerRegistrationReviewRequested($application->load('user')));
        }

        return ['application' => $application, 'created' => true];
    }
}
