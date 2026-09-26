<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CommunicationRecipientSegment
{
    public const ALL_MEMBERS = 'all_members';
    public const TEAM = 'team';

    /**
     * @return array{audience_type: string, club_id: int, team_id: int|null, recipient_count: int, recipient_hash: string}
     */
    public function snapshot(Club $club, string $audienceType, ?int $teamId = null): array
    {
        $recipientIds = $this->recipientIds($club, $audienceType, $teamId);

        return [
            'audience_type' => $this->normalizeAudienceType($audienceType),
            'club_id' => (int) $club->id,
            'team_id' => $teamId ? (int) $teamId : null,
            'recipient_count' => $recipientIds->count(),
            'recipient_hash' => $this->hash($recipientIds),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    public function recipientIds(Club $club, string $audienceType, ?int $teamId = null): Collection
    {
        $audienceType = $this->normalizeAudienceType($audienceType);

        if ($audienceType === self::TEAM) {
            if (! $teamId) {
                throw ValidationException::withMessages(['team_id' => __('organization.survey.team_required')]);
            }

            $team = Team::query()
                ->select(['id', 'club_id'])
                ->where('club_id', $club->id)
                ->findOrFail($teamId);

            $teamUserIds = $team->users()->pluck('users.id');

            return $this->activeClubMemberIds($club)
                ->intersect($teamUserIds)
                ->values();
        }

        return $this->activeClubMemberIds($club);
    }

    public function visibleTo(Club $club, string $audienceType, ?int $teamId, User $user): bool
    {
        return $this->recipientIds($club, $audienceType, $teamId)
            ->contains((int) $user->id);
    }

    private function normalizeAudienceType(string $audienceType): string
    {
        return $audienceType === self::TEAM ? self::TEAM : self::ALL_MEMBERS;
    }

    /**
     * @return Collection<int, int>
     */
    private function activeClubMemberIds(Club $club): Collection
    {
        return $club->users()
            ->where(function ($query): void {
                $query->whereNull('club_user.membership_status')
                    ->orWhereNotIn('club_user.membership_status', ['former', 'paused', 'pending']);
            })
            ->pluck('users.id')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     */
    private function hash(Collection $recipientIds): string
    {
        return hash('sha256', $recipientIds->implode('|'));
    }
}
