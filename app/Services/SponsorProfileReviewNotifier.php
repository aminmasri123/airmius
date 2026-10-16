<?php

namespace App\Services;

use App\Models\Sponsor;
use App\Models\SponsorMembership;
use App\Models\User;
use App\Support\AppNotification;

class SponsorProfileReviewNotifier
{
    public function reviewRequested(Sponsor $sponsor, User $actor): void
    {
        User::permission('system.manage')
            ->whereKeyNot($actor->id)
            ->get()
            ->each(fn (User $reviewer) => AppNotification::sendLocalized(
                $reviewer,
                'sponsor.profile_review_requested',
                'sponsor.notifications.review_requested_title',
                'sponsor.notifications.review_requested_body',
                ['sponsor' => $sponsor->name],
                [
                    'url' => route('sponsors.index', ['q' => $sponsor->name]),
                    'action_url' => route('sponsors.index', ['q' => $sponsor->name]),
                    'mobile_url' => 'airmius://admin/sponsors?sponsor_id='.$sponsor->id,
                    'deep_link' => 'airmius://admin/sponsors?sponsor_id='.$sponsor->id,
                    'sponsor_id' => $sponsor->id,
                    'verification_status' => $sponsor->verification_status,
                ],
                [
                    'category' => 'system',
                    'dedupe_key' => 'sponsor-profile-review:'.$sponsor->id.':'.$sponsor->verification_requested_at?->getTimestamp(),
                ],
            ));
    }

    public function reviewDecided(Sponsor $sponsor): void
    {
        if (! in_array($sponsor->verification_status, ['verified', 'rejected'], true)) {
            return;
        }

        $recipientIds = SponsorMembership::query()
            ->where('sponsor_id', $sponsor->id)
            ->where('status', SponsorMembership::STATUS_ACTIVE)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->push($sponsor->owner_user_id)
            ->filter()
            ->unique();

        User::query()->whereIn('id', $recipientIds->all())->get()->each(function (User $recipient) use ($sponsor) {
            $approved = $sponsor->verification_status === 'verified';
            AppNotification::sendLocalized(
                $recipient,
                'sponsor.profile_review_decided',
                $approved
                    ? 'sponsor.notifications.review_approved_title'
                    : 'sponsor.notifications.review_rejected_title',
                $approved
                    ? 'sponsor.notifications.review_approved_body'
                    : 'sponsor.notifications.review_rejected_body',
                ['sponsor' => $sponsor->name],
                [
                    'url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id]),
                    'action_url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id]),
                    'mobile_url' => 'airmius://sponsors?sponsor_id='.$sponsor->id,
                    'deep_link' => 'airmius://sponsors?sponsor_id='.$sponsor->id,
                    'sponsor_id' => $sponsor->id,
                    'verification_status' => $sponsor->verification_status,
                    'verification_note' => $sponsor->verification_note,
                ],
                ['category' => 'system'],
            );
        });
    }
}
