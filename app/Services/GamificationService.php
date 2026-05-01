<?php

namespace App\Services;

use App\Models\GamificationXpEvent;
use App\Models\GamificationRule;
use App\Models\User;
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
    ): ?GamificationXpEvent {
        $rule = $this->ruleFor($reason, $actorType);
        $baseAmount = $rule?->xp_amount ?? self::PLAYER_ACTIONS[$reason] ?? 0;

        if (! ($rule?->is_active ?? true) || $baseAmount <= 0 || $this->dailyLimitReached($user, $reason, $actorType)) {
            return $this->recordLimitedEvent($user, $reason, $source, $meta, $actorType, $baseAmount);
        }

        $multiplier = $this->trustMultiplier($user);
        $amount = max(1, (int) round($baseAmount * $multiplier));

        $event = $this->recordEvent($user, $reason, $amount, $source, $meta, $actorType, $baseAmount, $multiplier);

        if ($reason !== 'daily_meaningful_activity') {
            $this->recordDailyActivity($user, $event);
        }

        $this->adjustTrust($user, $rule?->trust_delta ?? 1);

        return $event;
    }

    public function penalize(
        User $user,
        string $reason,
        ?Model $source = null,
        array $meta = [],
        string $actorType = 'sportler',
    ): GamificationXpEvent {
        $rule = $this->ruleFor($reason, $actorType);
        $amount = $rule?->xp_amount ?? self::PENALTIES[$reason] ?? -5;

        $event = $this->recordEvent($user, $reason, $amount, $source, $meta, $actorType, $amount, 1);
        $this->adjustTrust($user, $rule?->trust_delta ?? $this->trustPenaltyFor($reason));

        return $event;
    }

    public function summaryFor(User $user): array
    {
        $xp = (int) $user->xpEvents()->sum('amount');
        $level = $this->levelForXp($xp);

        return [
            'xp' => $xp,
            'level' => $level['level'],
            'rank' => $this->rankFor('sportler', $level['level']),
            'title' => $level['title'],
            'next_level_xp' => $level['next_level_xp'],
            'current_level_xp' => $level['current_level_xp'],
            'progress' => $level['progress'],
            'trust_score' => (int) ($user->trust_score ?? 100),
            'trust_multiplier' => $this->trustMultiplier($user),
            'streak_days' => (int) ($user->gamification_streak_days ?? 0),
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
            );
        }
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
    ): ?GamificationXpEvent {
        if (! array_key_exists($reason, self::DAILY_LIMITS) && ! $this->ruleFor($reason, $actorType)?->daily_limit) {
            return null;
        }

        return $this->recordEvent($user, $reason, 0, $source, $meta, $actorType, $baseAmount, 1, true);
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
    ): GamificationXpEvent {
        return GamificationXpEvent::create([
            'user_id' => $user->id,
            'actor_type' => $actorType,
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
}
