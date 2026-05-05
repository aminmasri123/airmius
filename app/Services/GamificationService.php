<?php

namespace App\Services;

use App\Models\GamificationXpEvent;
use App\Models\GamificationRule;
use App\Models\Badge;
use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Models\UserBadge;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class GamificationService
{
    public const PLAYER_ACTIONS = [
        'sport_profile_added' => 15,
        'skill_profile_refined' => 3,
        'skill_level_improved' => 8,
        'skill_endorsed' => 10,
        'recommendation_approved' => 10,
        'training_accepted' => 5,
        'training_check_in' => 2,
        'content_created' => 5,
        'knowledge_marked_helpful' => 5,
        'daily_meaningful_activity' => 2,
    ];

    public const PENALTIES = [
        'training_no_show' => -5,
        'false_confirmation' => -50,
        'spam_or_abuse' => -30,
        'recommendation_rejected' => -5,
        'excessive_usage' => -5,
    ];

    public const DAILY_LIMITS = [
        'skill_profile_refined' => 3,
        'skill_level_improved' => 3,
        'skill_endorsed' => 3,
        'recommendation_approved' => 2,
        'training_accepted' => 3,
        'training_check_in' => 3,
        'content_created' => 3,
        'knowledge_marked_helpful' => 3,
    ];

    public const STREAK_BONUSES = [
        3 => 5,
        7 => 10,
        14 => 20,
        30 => 40,
    ];

    public function grant(
        User $user,
        string $reason,
        ?Model $source = null,
        array $meta = [],
        string $actorType = 'sportler',
        ?Model $owner = null,
    ): ?GamificationXpEvent {
        $owner ??= $user;
        $rule = $this->ruleFor($reason, $actorType);
        $baseAmount = $rule?->xp_amount ?? self::PLAYER_ACTIONS[$reason] ?? 0;

        if (! ($rule?->is_active ?? true) || $baseAmount <= 0 || $this->dailyLimitReached($user, $reason, $actorType)) {
            return $this->recordLimitedEvent($user, $reason, $source, $meta, $actorType, $baseAmount, $owner);
        }

        $multiplier = $this->trustMultiplier($user);
        $amount = max(1, (int) round($baseAmount * $multiplier));

        $event = $this->recordEvent($user, $reason, $amount, $source, $meta, $actorType, $baseAmount, $multiplier, false, $owner);

        if ($reason !== 'daily_meaningful_activity' && $owner instanceof User) {
            $this->recordDailyActivity($user, $event);
        }

        $this->adjustTrust($user, $rule?->trust_delta ?? 1);
        $this->awardEligibleBadges($user, $owner, $actorType, $reason);

        return $event;
    }

    public function grantToClub(
        User $actor,
        Club $club,
        string $reason,
        ?Model $source = null,
        array $meta = [],
    ): ?GamificationXpEvent {
        return $this->grant($actor, $reason, $source, $meta, 'verein', $club);
    }

    public function grantToTeam(
        User $actor,
        Team $team,
        string $reason,
        ?Model $source = null,
        array $meta = [],
    ): ?GamificationXpEvent {
        return $this->grant($actor, $reason, $source, $meta, 'team', $team);
    }

    public function grantToTrainer(
        User $trainer,
        string $reason,
        ?Model $source = null,
        array $meta = [],
    ): ?GamificationXpEvent {
        return $this->grant($trainer, $reason, $source, $meta, 'trainer', $trainer);
    }

    public function penalize(
        User $user,
        string $reason,
        ?Model $source = null,
        array $meta = [],
        string $actorType = 'sportler',
        ?Model $owner = null,
    ): GamificationXpEvent {
        $owner ??= $user;
        $rule = $this->ruleFor($reason, $actorType);
        $amount = $rule?->xp_amount ?? self::PENALTIES[$reason] ?? -5;

        $event = $this->recordEvent($user, $reason, $amount, $source, $meta, $actorType, $amount, 1, false, $owner);
        $this->adjustTrust($user, $rule?->trust_delta ?? $this->trustPenaltyFor($reason));

        return $event;
    }

    public function summaryFor(Model $owner, ?string $actorType = null): array
    {
        $actorType ??= $this->actorTypeForOwner($owner);
        $xp = (int) $this->xpQueryForOwner($owner, $actorType)->sum('amount');
        $level = $this->levelForXp($xp);

        return [
            'xp' => $xp,
            'level' => $level['level'],
            'rank' => $this->rankFor($actorType, $level['level']),
            'title' => $this->rankFor($actorType, $level['level']),
            'next_level_xp' => $level['next_level_xp'],
            'current_level_xp' => $level['current_level_xp'],
            'progress' => $level['progress'],
            'trust_score' => $owner instanceof User ? (int) ($owner->trust_score ?? 100) : null,
            'trust_multiplier' => $owner instanceof User ? $this->trustMultiplier($owner) : 1,
            'streak_days' => $owner instanceof User ? (int) ($owner->gamification_streak_days ?? 0) : 0,
        ];
    }

    public function levelForXp(int $xp): array
    {
        $level = 1;

        while ($level < 100 && $xp >= $this->xpNeededForLevel($level + 1)) {
            $level++;
        }

        $currentLevelXp = $this->xpNeededForLevel($level);
        $nextLevelXp = $this->xpNeededForLevel($level + 1);
        $range = max(1, $nextLevelXp - $currentLevelXp);
        $progress = min(100, (int) round((($xp - $currentLevelXp) / $range) * 100));

        return [
            'level' => $level,
            'title' => $this->rankFor('sportler', $level),
            'current_level_xp' => $currentLevelXp,
            'next_level_xp' => $nextLevelXp,
            'progress' => $progress,
        ];
    }

    public function xpNeededForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $total = 0;

        for ($currentLevel = 1; $currentLevel < $level; $currentLevel++) {
            $total += (int) round(250 * ($currentLevel ** 1.7));
        }

        return $total;
    }

    public function rankFor(string $actorType, int $level): string
    {
        if ($actorType === 'trainer') {
            return match (true) {
                $level >= 9 => 'Master Trainer',
                $level >= 7 => 'Head Coach',
                $level >= 5 => 'Senior Coach',
                $level >= 3 => 'Coach',
                default => 'Assistant',
            };
        }

        if ($actorType === 'verein') {
            return match (true) {
                $level >= 9 => 'Elite Club',
                $level >= 6 => 'Established',
                $level >= 3 => 'Growing Club',
                default => 'Local Club',
            };
        }

        return match (true) {
            $level >= 9 => 'Elite',
            $level >= 7 => 'Advanced',
            $level >= 5 => 'Athlete',
            $level >= 3 => 'Amateur',
            default => 'Rookie',
        };
    }

    public function trustMultiplier(User $user): float
    {
        $trust = max(70, min(130, (int) ($user->trust_score ?? 100)));

        return round($trust / 100, 2);
    }

    private function recordDailyActivity(User $user, GamificationXpEvent $sourceEvent): void
    {
        $today = Carbon::today();

        if ($this->hasEventOnDate($user, 'daily_meaningful_activity', $today)) {
            return;
        }

        $this->recordEvent(
            $user,
            'daily_meaningful_activity',
            self::PLAYER_ACTIONS['daily_meaningful_activity'],
            $sourceEvent,
            ['trigger_reason' => $sourceEvent->reason],
            $sourceEvent->actor_type,
            self::PLAYER_ACTIONS['daily_meaningful_activity'],
            1,
            false,
            $user,
        );

        $lastActive = $user->gamification_last_active_on
            ? Carbon::parse($user->gamification_last_active_on)
            : null;

        $streak = $lastActive?->isYesterday()
            ? ((int) $user->gamification_streak_days) + 1
            : 1;

        $user->forceFill([
            'gamification_streak_days' => $streak,
            'gamification_last_active_on' => $today->toDateString(),
        ])->save();

        if (isset(self::STREAK_BONUSES[$streak])) {
            $this->recordEvent(
                $user,
                'streak_bonus_'.$streak,
                self::STREAK_BONUSES[$streak],
                $sourceEvent,
                ['streak_days' => $streak],
                $sourceEvent->actor_type,
                self::STREAK_BONUSES[$streak],
                1,
                false,
                $user,
            );
        }

        $this->awardEligibleBadges($user, $user, $sourceEvent->actor_type, 'daily_meaningful_activity');
    }

    private function dailyLimitReached(User $user, string $reason, string $actorType = 'sportler'): bool
    {
        $limit = $this->ruleFor($reason, $actorType)?->daily_limit ?? self::DAILY_LIMITS[$reason] ?? null;

        if (! $limit) {
            return false;
        }

        return $user->xpEvents()
            ->where('reason', $reason)
            ->whereDate('created_at', Carbon::today())
            ->count() >= $limit;
    }

    private function hasEventOnDate(User $user, string $reason, CarbonInterface $date): bool
    {
        return $user->xpEvents()
            ->where('reason', $reason)
            ->whereDate('created_at', $date)
            ->exists();
    }

    private function recordLimitedEvent(
        User $user,
        string $reason,
        ?Model $source,
        array $meta,
        string $actorType,
        int $baseAmount,
        Model $owner,
    ): ?GamificationXpEvent {
        if (! array_key_exists($reason, self::DAILY_LIMITS) && ! $this->ruleFor($reason, $actorType)?->daily_limit) {
            return null;
        }

        return $this->recordEvent($user, $reason, 0, $source, $meta, $actorType, $baseAmount, 1, true, $owner);
    }

    private function recordEvent(
        User $user,
        string $reason,
        int $amount,
        ?Model $source,
        array $meta,
        string $actorType,
        ?int $baseAmount,
        float $trustMultiplier,
        bool $limited = false,
        ?Model $owner = null,
    ): GamificationXpEvent {
        $owner ??= $user;

        return GamificationXpEvent::create([
            'user_id' => $user->id,
            'actor_type' => $actorType,
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source->id ?? null,
            'amount' => $amount,
            'base_amount' => $baseAmount,
            'trust_multiplier' => $trustMultiplier,
            'limited_by_daily_cap' => $limited,
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }

    private function adjustTrust(User $user, int $delta): void
    {
        $current = (int) ($user->trust_score ?? 100);

        $user->forceFill([
            'trust_score' => max(70, min(130, $current + $delta)),
        ])->save();
    }

    private function trustPenaltyFor(string $reason): int
    {
        return match ($reason) {
            'false_confirmation' => -12,
            'spam_or_abuse' => -8,
            'training_no_show' => -3,
            'recommendation_rejected' => -2,
            default => -1,
        };
    }

    private function ruleFor(string $reason, string $actorType): ?GamificationRule
    {
        static $rules = [];

        $cacheKey = $actorType.'.'.$reason;

        if (array_key_exists($cacheKey, $rules)) {
            return $rules[$cacheKey];
        }

        return $rules[$cacheKey] = GamificationRule::query()
            ->where('key', $reason)
            ->where('actor_type', $actorType)
            ->first();
    }

    private function xpQueryForOwner(Model $owner, ?string $actorType = null)
    {
        $query = GamificationXpEvent::query();

        if ($owner instanceof User) {
            $query->where(function ($query) use ($owner) {
                $query->where(function ($query) use ($owner) {
                    $query->where('owner_type', User::class)
                        ->where('owner_id', $owner->id);
                })->orWhere(function ($query) use ($owner) {
                    $query->whereNull('owner_type')
                        ->where('user_id', $owner->id);
                });
            });
        } else {
            $query
                ->where('owner_type', $owner::class)
                ->where('owner_id', $owner->id);
        }

        return $query->when($actorType, fn ($query) => $query->where('actor_type', $actorType));
    }

    private function actorTypeForOwner(Model $owner): string
    {
        return match (true) {
            $owner instanceof Club => 'verein',
            $owner instanceof Team => 'team',
            $owner instanceof User && $owner->hasAnyRole(['coach', 'assistant_coach', 'performance_coach', 'fitness_coach']) => 'trainer',
            default => 'sportler',
        };
    }

    private function awardEligibleBadges(User $actor, Model $owner, string $actorType, string $reason): void
    {
        $summary = $this->summaryFor($owner, $actorType);

        Badge::query()
            ->where('actor_type', $actorType)
            ->get()
            ->each(function (Badge $badge) use ($actor, $owner, $reason, $summary) {
                if (! $this->badgeMatches($badge, $reason, $summary)) {
                    return;
                }

                $exists = UserBadge::query()
                    ->where('badge_id', $badge->id)
                    ->where('awardable_type', $owner::class)
                    ->where('awardable_id', $owner->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                UserBadge::create([
                    'user_id' => $actor->id,
                    'badge_id' => $badge->id,
                    'awardable_type' => $owner::class,
                    'awardable_id' => $owner->id,
                    'reason' => $reason,
                    'meta' => [
                        'xp' => $summary['xp'],
                        'level' => $summary['level'],
                        'streak_days' => $summary['streak_days'],
                    ],
                ]);
            });
    }

    private function badgeMatches(Badge $badge, string $reason, array $summary): bool
    {
        return match ($badge->trigger) {
            'xp' => $summary['xp'] >= $badge->threshold,
            'level' => $summary['level'] >= $badge->threshold,
            'streak' => $summary['streak_days'] >= $badge->threshold,
            'reason' => ($badge->meta['reason'] ?? null) === $reason,
            default => false,
        };
    }
}
