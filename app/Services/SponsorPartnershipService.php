<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorPartnership;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SponsorPartnershipService
{
    public function __construct(private SponsorOrganizationService $organizations) {}

    public function create(User $user, array $data): SponsorPartnership
    {
        $sponsor = $this->managedSponsor($user, (int) $data['sponsor_id']);
        $club = Club::query()->visibleTo($user)->findOrFail((int) $data['club_id']);

        $partnership = DB::transaction(function () use ($user, $data, $sponsor, $club) {
            $duplicate = SponsorPartnership::query()
                ->where('sponsor_id', $sponsor->id)
                ->where('club_id', $club->id)
                ->whereIn('status', [
                    SponsorPartnership::STATUS_PENDING,
                    SponsorPartnership::STATUS_ACTIVE,
                ])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'club_id' => __('sponsor.partnership.duplicate'),
                ]);
            }

            return SponsorPartnership::query()->create([
                ...$data,
                'sponsor_id' => $sponsor->id,
                'club_id' => $club->id,
                'created_by' => $user->id,
                'status' => SponsorPartnership::STATUS_PENDING,
            ]);
        });

        $this->organizations->audit($sponsor, $user, 'partnership.requested', [
            'partnership_id' => $partnership->id,
            'club_id' => $club->id,
            'club_name' => $club->name,
        ]);
        $this->notifyClub($club, $sponsor, $partnership);

        return $partnership->load('club:id,name,logo,website_url');
    }

    public function withdraw(User $user, SponsorPartnership $partnership): SponsorPartnership
    {
        $sponsor = $this->managedSponsor($user, (int) $partnership->sponsor_id);
        abort_unless($partnership->status === SponsorPartnership::STATUS_PENDING, 422, __('sponsor.partnership.withdraw_unavailable'));

        $partnership->update([
            'status' => SponsorPartnership::STATUS_WITHDRAWN,
            'responded_by' => $user->id,
            'responded_at' => now(),
        ]);
        $this->organizations->audit($sponsor, $user, 'partnership.withdrawn', [
            'partnership_id' => $partnership->id,
            'club_id' => $partnership->club_id,
        ]);

        return $partnership->fresh('club:id,name,logo,website_url');
    }

    public function respond(User $user, SponsorPartnership $partnership, bool $approve, ?string $note = null): SponsorPartnership
    {
        $partnership->loadMissing(['club', 'sponsor.memberships.user']);
        abort_unless(
            $user->can('system.manage')
                || ClubPermissions::allows($partnership->club, $user, ClubPermissions::SPONSORS_EDIT),
            403,
        );
        abort_unless($partnership->status === SponsorPartnership::STATUS_PENDING, 409, __('sponsor.partnership.already_decided'));

        $partnership->update([
            'status' => $approve ? SponsorPartnership::STATUS_ACTIVE : SponsorPartnership::STATUS_REJECTED,
            'response_note' => $note,
            'responded_by' => $user->id,
            'responded_at' => now(),
        ]);
        $this->organizations->audit($partnership->sponsor, $user, $approve ? 'partnership.approved' : 'partnership.rejected', [
            'partnership_id' => $partnership->id,
            'club_id' => $partnership->club_id,
            'response_note' => $note,
        ]);

        $recipients = collect([$partnership->sponsor->owner])
            ->merge($partnership->sponsor->memberships->pluck('user'))
            ->filter()
            ->unique('id');
        $recipients->each(fn (User $recipient) => AppNotification::sendLocalized(
            $recipient,
            $approve ? 'sponsor.partnership_approved' : 'sponsor.partnership_rejected',
            $approve ? 'sponsor.partnership.approved_title' : 'sponsor.partnership.rejected_title',
            $approve ? 'sponsor.partnership.approved_body' : 'sponsor.partnership.rejected_body',
            ['club' => $partnership->club->name],
            [
                'url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $partnership->sponsor_id]),
                'mobile_url' => 'airmius://sponsors?sponsor_id='.$partnership->sponsor_id,
                'sponsor_id' => $partnership->sponsor_id,
                'club_id' => $partnership->club_id,
                'partnership_id' => $partnership->id,
                'status' => $approve ? SponsorPartnership::STATUS_ACTIVE : SponsorPartnership::STATUS_REJECTED,
                'response_note' => $note,
            ],
        ));

        return $partnership->fresh(['club:id,name,logo,website_url', 'sponsor:id,name']);
    }

    public function findForSponsorUser(User $user, int $partnershipId): SponsorPartnership
    {
        $partnership = SponsorPartnership::query()
            ->with('club:id,name,logo,website_url')
            ->findOrFail($partnershipId);

        // Return 404 for another sponsor's record so IDs cannot be enumerated.
        abort_unless($this->organizations->can($user, $partnership->sponsor, 'view'), 404);

        return $partnership;
    }

    private function managedSponsor(User $user, int $sponsorId): Sponsor
    {
        $sponsor = Sponsor::query()->findOrFail($sponsorId);
        abort_unless($this->organizations->can($user, $sponsor, 'partnerships'), 403);

        return $sponsor;
    }

    private function notifyClub(Club $club, Sponsor $sponsor, SponsorPartnership $partnership): void
    {
        $recipients = collect([$club->owner])
            ->merge($club->users()->get())
            ->filter(fn (?User $candidate) => $candidate
                && ClubPermissions::allows($club, $candidate, ClubPermissions::SPONSORS_EDIT))
            ->unique('id');

        $recipients->each(fn (User $recipient) => AppNotification::sendLocalized(
            $recipient,
            'sponsor.partnership_requested',
            'sponsor.partnership.notification_title',
            'sponsor.partnership.notification_body',
            ['sponsor' => $sponsor->name, 'club' => $club->name],
            [
                'url' => route('auth.teams.index', ['club_id' => $club->id, 'section' => 'sponsors']),
                'mobile_url' => 'airmius://admin/sponsors?club_id='.$club->id,
                'mobile_deep_link' => 'airmius://admin/sponsors?club_id='.$club->id,
                'sponsor_id' => $sponsor->id,
                'club_id' => $club->id,
                'partnership_id' => $partnership->id,
                'status' => $partnership->status,
            ],
        ));
    }
}
