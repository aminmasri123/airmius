<?php

namespace App\Services;

use App\Models\ModerationFlag;
use App\Models\AccountWarning;
use App\Models\User;
use App\Notifications\AccountSuspendedNotification;
use Illuminate\Database\Eloquent\Model;

class ModerationService
{
    private const TERMS = [
        'insult' => [
            'severity' => 'medium',
            'terms' => [
                'arschloch',
                'idiot',
                'hurensohn',
                'fotze',
                'wichser',
                'spast',
                'bastard',
            ],
        ],
        'threat' => [
            'severity' => 'high',
            'terms' => [
                'ich bring dich um',
                'ich töte dich',
                'ich schlage dich',
                'du bist tot',
                'ich mache dich fertig',
            ],
        ],
        'hate' => [
            'severity' => 'high',
            'terms' => [
                'heil hitler',
                'sieg heil',
                'vergasen',
                'ausrotten',
            ],
        ],
        'sexual' => [
            'severity' => 'high',
            'terms' => [
                'nacktfoto',
                'nudes',
                'schick nudes',
                'sex mit',
                'porn',
            ],
        ],
        'spam' => [
            'severity' => 'medium',
            'terms' => [
                'gratis geld',
                'klick hier',
                'whatsapp gruppe',
                'bitcoin gewinn',
                'casino bonus',
            ],
        ],
    ];

    private const SEVERITY_SCORE = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
    ];

    private const WARNING_POINTS = [
        'low' => 0,
        'medium' => 1,
        'high' => 3,
    ];

    private array $normalizedTermsCache = [];

    public function __construct()
    {
        // Begriffe einmalig beim Instanziieren normalisieren
        foreach (self::TERMS as $category => $config) {
            $this->normalizedTermsCache[$category] = array_map([$this, 'normalize'], $config['terms']);
        }
    }

    public function analyze(?string $text): array
    {
        $normalized = $this->normalize($text ?? '');
        $categories = [];
        $matchedTerms = [];
        $severity = 'low';

        if ($normalized === '') {
            return [
                'flagged' => false,
                'severity' => 'low',
                'categories' => [],
                'matched_terms' => [],
            ];
        }

        foreach ($this->normalizedTermsCache as $category => $terms) {
            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    $categories[] = $category;
                    $matchedTerms[] = $term;
                    $severity = $this->maxSeverity($severity, self::TERMS[$category]['severity']);
                }
            }
        }

        if (preg_match('/https?:\/\/\S+/i', $text ?? '') && preg_match('/(gratis|gewinn|casino|crypto|bitcoin)/iu', $text ?? '')) {
            $categories[] = 'spam';
            $matchedTerms[] = 'spam-link';
            $severity = $this->maxSeverity($severity, 'medium');
        }

        if (preg_match('/(.)\1{8,}/u', $normalized)) {
            $categories[] = 'spam';
            $matchedTerms[] = 'many-repeated-characters';
            $severity = $this->maxSeverity($severity, 'low');
        }

        return [
            'flagged' => $categories !== [],
            'severity' => $severity,
            'categories' => array_values(array_unique($categories)),
            'matched_terms' => array_values(array_unique($matchedTerms)),
        ];
    }

    public function flagIfNeeded(Model $model, ?string $text, ?int $userId = null, string $source = 'automatic'): ?ModerationFlag
    {
        $result = $this->analyze($text);

        if (! $result['flagged']) {
            return null;
        }

        $automatedAction = $result['severity'] === 'high' ? 'content_held_warning' : 'review_warning';

        if ($model->isFillable('moderation_status')) {
            $model->forceFill([
                'moderation_status' => $result['severity'] === 'high' ? 'removed' : 'flagged',
            ])->save();
        }

        $flag = ModerationFlag::create([
            'flaggable_type' => $model::class,
            'flaggable_id' => $model->getKey(),
            'user_id' => $userId,
            'source' => $source,
            'severity' => $result['severity'],
            'categories' => $result['categories'],
            'matched_terms' => $result['matched_terms'],
            'automated_action' => $automatedAction,
        ]);

        $this->warnAndSuspendIfNeeded($flag);

        return $flag;
    }

    private function warnAndSuspendIfNeeded(ModerationFlag $flag): void
    {
        if (! $flag->user_id) {
            return;
        }

        $points = self::WARNING_POINTS[$flag->severity] ?? 0;

        if ($points <= 0) {
            return;
        }

        AccountWarning::create([
            'user_id' => $flag->user_id,
            'moderation_flag_id' => $flag->id,
            'severity' => $flag->severity,
            'points' => $points,
            'reason' => 'Automatische Moderation: '.implode(', ', $flag->categories ?: []),
        ]);

        $this->suspendIfThresholdReached($flag->user_id);
    }

    private function suspendIfThresholdReached(int $userId): void
    {
        $since = now()->subDays(90);
        $warnings = AccountWarning::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->get();

        $points = $warnings->sum('points');
        $highCount = $warnings->where('severity', 'high')->count();

        if ($points < 5 && $highCount < 2) {
            return;
        }

        $user = User::query()
            ->whereKey($userId)
            ->where('account_status', '!=', 'suspended')
            ->first();

        if (! $user) {
            return;
        }

        $suspendedUntil = now()->addDays($highCount >= 2 ? 14 : 7);
        $reason = $highCount >= 2
            ? 'Automatische Sperre nach zwei schweren Moderationsverstoessen.'
            : 'Automatische Sperre nach wiederholten Moderationsverstoessen.';

        $user->forceFill([
            'account_status' => 'suspended',
            'suspended_until' => $suspendedUntil,
            'suspension_reason' => $reason,
        ])->save();

        try {
            $user->notify(new AccountSuspendedNotification($reason, $suspendedUntil->format('d.m.Y H:i')));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(trim($text));
    }

    private function maxSeverity(string $current, string $candidate): string
    {
        return self::SEVERITY_SCORE[$candidate] > self::SEVERITY_SCORE[$current]
            ? $candidate
            : $current;
    }
}
