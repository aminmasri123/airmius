<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Models\SponsorMembership;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SponsorInquiryService
{
    public function __construct(private SponsorOrganizationService $organizations) {}

    public function availableClubs(User $user)
    {
        return Club::query()->linkedToUser($user)->get(['id', 'owner_id', 'name', 'city', 'postal_code'])
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::SPONSORS_EDIT))
            ->values();
    }

    public function create(User $user, array $data): SponsorInquiry
    {
        $club = Club::query()->findOrFail((int) $data['club_id']);
        abort_unless(ClubPermissions::allows($club, $user, ClubPermissions::SPONSORS_EDIT), 403);
        $sponsor = Sponsor::query()->publiclyVerified()->whereNull('club_id')
            ->where('scope', 'platform')->findOrFail((int) $data['sponsor_id']);
        $recipientIds = SponsorMembership::query()
            ->where('sponsor_id', $sponsor->id)
            ->where('status', SponsorMembership::STATUS_ACTIVE)
            ->whereNotNull('user_id')->pluck('user_id')
            ->push($sponsor->owner_user_id)->filter()->unique();
        $recipients = User::query()->whereIn('id', $recipientIds)->get()
            ->filter(fn (User $recipient) => $this->organizations->can($recipient, $sponsor, 'partnerships'));
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'sponsor_id' => 'Dieser Sponsor kann derzeit keine Anfragen empfangen.',
            ]);
        }

        $inquiry = DB::transaction(function () use ($user, $data, $club, $sponsor): SponsorInquiry {
            Sponsor::query()->whereKey($sponsor->id)->lockForUpdate()->firstOrFail();
            if (SponsorInquiry::query()->where('sponsor_id', $sponsor->id)
                ->where('club_id', $club->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['sponsor_id' => 'Für diesen Sponsor besteht bereits eine offene Anfrage.']);
            }

            return SponsorInquiry::query()->create([
                'sponsor_id' => $sponsor->id,
                'club_id' => $club->id,
                'created_by' => $user->id,
                'subject' => trim($data['subject']),
                'message' => trim($data['message']),
            ]);
        });

        $recipients->each(fn (User $recipient) => AppNotification::sendLocalized(
                $recipient, 'sponsor.inquiry_requested',
                'sponsor.notifications.inquiry_title', 'sponsor.notifications.inquiry_body',
                ['club' => $club->name],
                [
                    'url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id, 'section' => 'partnerships']),
                    'mobile_url' => 'airmius://sponsors?sponsor_id='.$sponsor->id.'&section=partnerships',
                    'sponsor_id' => $sponsor->id,
                    'inquiry_id' => $inquiry->id,
                ],
                ['category' => 'system', 'dedupe_key' => 'sponsor-inquiry:'.$inquiry->id],
            ));

        return $inquiry;
    }

    public function respond(User $user, SponsorInquiry $inquiry, string $decision, ?string $note): SponsorInquiry
    {
        abort_unless($this->organizations->can($user, $inquiry->sponsor, 'partnerships'), 403);
        $updated = SponsorInquiry::query()->whereKey($inquiry->id)->where('status', 'pending')->update([
            'status' => $decision,
            'response_note' => $note,
            'responded_by' => $user->id,
            'responded_at' => now(),
        ]);
        abort_unless($updated === 1, 409, 'Diese Anfrage wurde bereits bearbeitet.');

        $club = $inquiry->club;
        $recipients = collect([$club->owner])->merge($club->users()->get())
            ->filter(fn (?User $candidate) => $candidate && ClubPermissions::allows($club, $candidate, ClubPermissions::SPONSORS_EDIT))
            ->unique('id');
        $recipients->each(fn (User $recipient) => AppNotification::sendLocalized(
            $recipient, 'sponsor.inquiry_decided',
            'sponsor.notifications.inquiry_decided_title', 'sponsor.notifications.inquiry_decided_body',
            ['sponsor' => $inquiry->sponsor->name],
            [
                'url' => route('guest.sponsors', ['sponsor_id' => $inquiry->sponsor_id]),
                'mobile_url' => 'airmius://sponsor-directory?sponsor_id='.$inquiry->sponsor_id,
                'inquiry_id' => $inquiry->id,
                'status' => $decision,
            ],
            ['category' => 'system'],
        ));

        return $inquiry->fresh();
    }

    public function withdraw(User $user, SponsorInquiry $inquiry): SponsorInquiry
    {
        abort_unless(ClubPermissions::allows($inquiry->club, $user, ClubPermissions::SPONSORS_EDIT), 403);
        $updated = SponsorInquiry::query()->whereKey($inquiry->id)->where('status', 'pending')
            ->update(['status' => 'withdrawn']);
        abort_unless($updated === 1, 409, 'Nur offene Anfragen können zurückgezogen werden.');

        return $inquiry->fresh();
    }

    public function outgoing(User $user)
    {
        $clubIds = $this->availableClubs($user)->pluck('id');

        return SponsorInquiry::query()->whereIn('club_id', $clubIds)
            ->latest('id')->limit(100)->get();
    }

    public function incoming(User $user, ?int $sponsorId)
    {
        if (! $sponsorId) {
            return collect();
        }
        $sponsor = Sponsor::query()->findOrFail($sponsorId);
        abort_unless($this->organizations->can($user, $sponsor, 'view'), 403);

        return SponsorInquiry::query()->with('club:id,name,city')
            ->where('sponsor_id', $sponsorId)->latest('id')->limit(100)->get();
    }
}
