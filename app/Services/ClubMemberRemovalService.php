<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubMemberRemoval;
use App\Models\ClubMembershipRequest;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubRoleLifecycle;
use Illuminate\Support\Facades\DB;

class ClubMemberRemovalService
{
    public function request(Club $club, User $member, User $actor, string $reason): ClubMemberRemoval
    {
        return DB::transaction(function () use ($club, $member, $actor, $reason) {
            // Serialize requests even when no removal row exists yet.
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $previous = ClubMemberRemoval::where('club_id', $club->id)->where('user_id', $member->id)->latest('id')->lockForUpdate()->first();
            if ($previous && in_array($previous->status, ['pending', 'objected'], true)) {
                return $previous;
            }
            $membership = $club->users()->where('users.id', $member->id)->first()?->pivot;
            $external = $club->externalMembers()->where('linked_user_id', $member->id)->first();
            abort_unless($membership || $external, 404);
            abort_if((int) $club->owner_id === (int) $member->id, 422);
            $removal = ClubMemberRemoval::create([
                'club_id' => $club->id, 'user_id' => $member->id,
                'previous_status' => $membership?->membership_status ?? $external?->membership_status,
                'status' => 'pending', 'reason' => $reason, 'expires_at' => now()->addDays(30),
                'objection' => null, 'responded_at' => null, 'completed_at' => null,
            ]);
            $club->users()->updateExistingPivot($member->id, ['membership_status' => 'removal_pending']);
            $club->externalMembers()->where('linked_user_id', $member->id)->update(['membership_status' => 'removal_pending']);
            ClubAuditLog::record($club, $actor, 'club.member.removal_requested', $member, ['removal_id' => $removal->id, 'reason' => $reason, 'expires_at' => $removal->expires_at->toIso8601String()]);
            AppNotification::sendLocalized($member, 'club.member_removal_pending', 'member_removal.title', 'member_removal.body', [
                'club' => $club->name, 'reason' => $reason, 'date' => $removal->expires_at->format('d.m.Y H:i'),
            ], [
                'club_id' => $club->id, 'removal_id' => $removal->id,
                'url' => '/member-removals/'.$removal->id, 'mobile_url' => 'airmius://notifications',
            ], ['after_commit' => true]);

            return $removal;
        });
    }

    public function respond(ClubMemberRemoval $removal, User $member, string $decision, ?string $message): ClubMemberRemoval
    {
        return DB::transaction(function () use ($removal, $member, $decision, $message) {
            $removal = ClubMemberRemoval::lockForUpdate()->findOrFail($removal->id);
            abort_unless((int) $removal->user_id === (int) $member->id, 404);
            abort_unless(in_array($decision, ['confirm', 'object'], true), 422);
            if ($removal->status !== 'pending') {
                return $removal;
            }
            if ($removal->expires_at->lessThanOrEqualTo(now())) {
                $this->finish($removal, $member);

                return $removal->fresh();
            }
            $club = Club::findOrFail($removal->club_id);
            if ($decision === 'object') {
                $removal->update(['status' => 'objected', 'objection' => $message, 'responded_at' => now()]);
                $club->users()->updateExistingPivot($member->id, ['membership_status' => 'removal_objected']);
                $club->externalMembers()->where('linked_user_id', $member->id)->update(['membership_status' => 'removal_objected']);
                ClubMembershipRequest::create([
                    'club_id' => $club->id, 'user_id' => $member->id, 'type' => 'removal_objection',
                    'status' => 'pending', 'message' => $message ?: 'Ich widerspreche der Entfernung.',
                    'application_data' => ['removal_id' => $removal->id],
                ]);
                ClubAuditLog::record($club, $member, 'club.member.removal_objected', $member, ['removal_id' => $removal->id]);
                AppNotification::sendLocalized($club->owner_id, 'club.member_removal_objected', 'member_removal.objected_title', 'member_removal.objected_body', ['user' => $member->name, 'club' => $club->name], ['url' => route('auth.club-memberships.index'), 'club_id' => $club->id], ['after_commit' => true]);
            } else {
                $removal->update(['responded_at' => now()]);
                $this->finish($removal, $member);
            }

            return $removal->fresh();
        });
    }

    public function expire(): int
    {
        $count = 0;
        ClubMemberRemoval::where('status', 'pending')->where('expires_at', '<=', now())->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $count += DB::transaction(function () use ($row) {
                    $removal = ClubMemberRemoval::lockForUpdate()->find($row->id);
                    if (! $removal || $removal->status !== 'pending' || $removal->expires_at->isFuture()) {
                        return 0;
                    }
                    $this->finish($removal, User::findOrFail($removal->user_id));

                    return 1;
                });
            }
        });

        return $count;
    }

    private function finish(ClubMemberRemoval $removal, User $member): void
    {
        $club = Club::findOrFail($removal->club_id);
        DB::table('team_user')->whereIn('team_id', $club->teams()->pluck('id'))->where('user_id', $member->id)->delete();
        app(ClubMetadataSubjectService::class)->clear($club, 'member', $member->id);
        app(ClubRoleLifecycle::class)->clearForMembership($club, $member, $member);
        $club->users()->detach($member->id);
        foreach ($club->externalMembers()->where('linked_user_id', $member->id)->get() as $external) {
            $external->delete();
        }
        app(ClubService::class)->refreshClubOwnerRole($member);
        $removal->update(['status' => 'completed', 'completed_at' => now()]);
        ClubAuditLog::record($club, $member, 'club.member.removed', $member, ['removal_id' => $removal->id, 'automatic' => $removal->responded_at === null]);
        AppNotification::sendLocalized($member, 'club.member_removed', 'member_removal.completed_title', 'member_removal.completed_body', ['club' => $club->name], ['url' => '/member-removals/'.$removal->id, 'mobile_url' => 'airmius://notifications', 'removal_id' => $removal->id], ['after_commit' => true]);
    }
}
