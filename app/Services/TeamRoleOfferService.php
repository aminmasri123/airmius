<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamRoleOffer;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeamRoleOfferService
{
    public function change(Team $team, User $actor, User $recipient, string $role): ?TeamRoleOffer
    {
        return DB::transaction(function () use ($team, $actor, $recipient, $role) {
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            Gate::forUser($actor)->authorize('updateMemberRole', $team);
            abort_unless(in_array($role, Team::ROLES, true), 422);
            $member = $team->users()->whereKey($recipient->id)->firstOrFail();
            $previous = $member->pivot->role;
            // Old membership invitations must not restore a role after it was withdrawn.
            $this->cancelMembershipInvitations($team, $recipient);
            // Resending the same offer is idempotent; selecting the active role cancels pending offers.
            $pending = TeamRoleOffer::where('team_id', $team->id)->where('recipient_id', $recipient->id)->where('status', 'pending');
            if ($role !== $previous && $role !== TeamRoles::PLAYER) {
                $existing = (clone $pending)->where('proposer_id', $actor->id)->where('role', $role)->where('previous_role', $previous)->where('expires_at', '>', now())->first();
                if ($existing) {
                    return $existing;
                }
            }
            $pending->update(['status' => 'cancelled', 'responded_at' => now()]);
            if ($role === $previous) {
                return null;
            }
            if ($role === TeamRoles::PLAYER) {
                $team->users()->updateExistingPivot($recipient->id, ['role' => $role]);
                DB::afterCommit(fn () => $this->notify($team, $recipient, 'updated', $previous, $role));

                return null;
            }
            $offer = TeamRoleOffer::create([
                'team_id' => $team->id, 'recipient_id' => $recipient->id, 'proposer_id' => $actor->id,
                'previous_role' => $previous, 'role' => $role, 'membership_created_at' => $member->pivot->created_at,
                'expires_at' => now()->addDays(14),
            ]);
            DB::afterCommit(fn () => $this->notify($team, $recipient, 'offered', $previous, $role, $offer));

            return $offer;
        });
    }

    public function cancelMembershipInvitations(Team $team, User $recipient): void
    {
        TeamInvitation::where('team_id', $team->id)->where('status', 'pending')
            ->where(fn ($q) => $q->where('recipient_id', $recipient->id)->orWhereRaw('LOWER(email) = ?', [mb_strtolower($recipient->email)]))
            ->update(['status' => 'cancelled', 'responded_at' => now()]);
    }

    public function respond(TeamRoleOffer $offer, User $actor, string $decision): void
    {
        DB::transaction(function () use ($offer, $actor, $decision) {
            $team = Team::query()->lockForUpdate()->findOrFail($offer->team_id);
            $offer = TeamRoleOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($decision === 'cancel') {
                Gate::forUser($actor)->authorize('updateMemberRole', $team);
            } else {
                abort_unless($actor->id === $offer->recipient_id, 403);
            }
            if ($offer->status !== 'pending') {
                abort_unless($offer->status === ['accept' => 'accepted', 'decline' => 'declined', 'cancel' => 'cancelled'][$decision], 409);

                return;
            }
            if ($decision === 'accept') {
                $member = $team->users()->whereKey($actor->id)->first();
                abort_unless($offer->expires_at->isFuture() && $member
                    && $member->pivot->role === $offer->previous_role
                    && $member->pivot->created_at?->equalTo($offer->membership_created_at)
                    && $offer->proposer && Gate::forUser($offer->proposer)->allows('updateMemberRole', $team), 409, __('team_role_offers.stale'));
                $team->users()->updateExistingPivot($actor->id, ['role' => $offer->role]);
            }
            $status = ['accept' => 'accepted', 'decline' => 'declined', 'cancel' => 'cancelled'][$decision];
            $offer->update(['status' => $status, 'responded_at' => now()]);
            DB::afterCommit(function () use ($team, $offer, $status) {
                $this->notify($team, $offer->recipient, $status, $offer->previous_role, $offer->role, $offer);
                if ($offer->proposer_id !== $offer->recipient_id) {
                    $this->notify($team, $offer->proposer, $status, $offer->previous_role, $offer->role, $offer);
                }
            });
        });
    }

    public function visible(Team $team, User $viewer): array
    {
        $manager = $viewer->can('updateMemberRole', $team);

        return TeamRoleOffer::where('team_id', $team->id)->where('status', 'pending')->where('expires_at', '>', now())
            ->when(! $manager, fn ($q) => $q->where('recipient_id', $viewer->id))
            ->with('recipient:id,name')->get()->map(fn ($offer) => [
                'id' => $offer->id, 'recipient_id' => $offer->recipient_id, 'recipient_name' => $offer->recipient?->name,
                'role' => $offer->role, 'previous_role' => $offer->previous_role, 'expires_at' => $offer->expires_at->toJSON(),
                'can_respond' => $offer->recipient_id === $viewer->id, 'can_cancel' => $manager,
            ])->all();
    }

    private function notify(Team $team, User $recipient, string $event, string $previous, string $role, ?TeamRoleOffer $offer = null): void
    {
        AppNotification::sendLocalized($recipient, 'team.role_'.$event, 'team_role_offers.'.$event.'_title', 'team_role_offers.'.$event.'_body', [
            'team' => $team->name,
            'role' => AppNotification::translatedReplacement('organization.roles.team.'.strtolower($role), TeamRoles::definition($role)['label'] ?? $role),
            'member' => $offer?->recipient?->name ?? $recipient->name,
        ], [
            'url' => '/teams/'.$team->id, 'mobile_url' => 'airmius://teams/'.$team->id,
            'team_id' => $team->id, 'club_id' => $team->club_id, 'role_offer_id' => $offer?->id,
            'role' => $role, 'previous_role' => $previous,
        ]);
    }
}
