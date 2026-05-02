<?php

namespace App\Services;

use App\Models\ModerationFlag;
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

        if ($model->isFillable('moderation_status')) {
            $model->forceFill([
                'moderation_status' => $result['severity'] === 'high' ? 'flagged_high' : 'flagged',
            ])->save();
        }

        return ModerationFlag::create([
            'flaggable_type' => $model::class,
            'flaggable_id' => $model->getKey(),
            'user_id' => $userId,
            'source' => $source,
            'severity' => $result['severity'],
            'categories' => $result['categories'],
            'matched_terms' => $result['matched_terms'],
        ]);
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
