<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\ExternalProviderUsageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AirmiusAiService
{
    public function __construct(
        private readonly AiImagePrivacyService $images,
        private readonly ExternalProviderUsageService $usage,
    ) {
    }

    public function capabilities(): array
    {
        $providers = $this->availableProviders();
        $feature = config('airmius_ai.features.nutrition_image_analysis', []);

        return [
            'enabled' => (bool) config('airmius_ai.enabled', true),
            'available_providers' => $providers,
            'primary_provider' => config('airmius_ai.primary_provider', 'google'),
            'fallback_provider' => config('airmius_ai.fallback_provider', 'openai'),
            'privacy' => [
                'exif_removed' => true,
                'store_uploads' => (bool) config('airmius_ai.privacy.store_uploads', false),
                'max_image_kb' => (int) config('airmius_ai.privacy.max_image_kb', 5120),
                'requires_confirmation' => true,
            ],
            'nutrition_image_analysis' => [
                'enabled' => (bool) ($feature['enabled'] ?? true),
                'available' => (bool) config('airmius_ai.enabled', true)
                    && (bool) ($feature['enabled'] ?? true)
                    && $providers !== [],
                'primary_provider' => $feature['primary_provider'] ?? config('airmius_ai.primary_provider', 'google'),
                'fallback_provider' => $feature['fallback_provider'] ?? config('airmius_ai.fallback_provider', 'openai'),
            ],
        ];
    }

    public function analyzeNutritionImage(User $user, UploadedFile $image, array $context = []): array
    {
        if (! (bool) config('airmius_ai.enabled', true)) {
            throw new RuntimeException('KI-Funktionen sind aktuell deaktiviert.');
        }

        $feature = config('airmius_ai.features.nutrition_image_analysis', []);

        if (! (bool) ($feature['enabled'] ?? true)) {
            throw new RuntimeException('KI-Bildanalyse ist aktuell deaktiviert.');
        }

        $preparedImage = $this->images->prepareForVision($image);
        $providers = array_values(array_unique(array_filter([
            $feature['primary_provider'] ?? config('airmius_ai.primary_provider', 'google'),
            $feature['fallback_provider'] ?? config('airmius_ai.fallback_provider', 'openai'),
        ])));

        $lastError = null;

        foreach ($providers as $provider) {
            if (! $this->providerAvailable($provider)) {
                continue;
            }

            try {
                $result = match ($provider) {
                    'google' => $this->analyzeNutritionImageWithGoogle($preparedImage, $context),
                    'openai' => $this->analyzeNutritionImageWithOpenAi($preparedImage, $context),
                    'ionos' => $this->analyzeNutritionImageWithOpenAiCompatible($provider, $preparedImage, $context),
                    default => throw new RuntimeException('Unbekannter KI-Anbieter.'),
                };

                $this->recordUsage($user, $provider, $result, $preparedImage, 'ok');

                return [
                    ...$this->normalizeNutritionResult($result['data'] ?? []),
                    'provider' => $provider,
                    'provider_label' => config("airmius_ai.providers.{$provider}.label", $provider),
                    'model' => $result['model'] ?? config("airmius_ai.providers.{$provider}.model"),
                    'privacy' => $preparedImage['privacy'],
                    'needs_user_confirmation' => true,
                ];
            } catch (Throwable $exception) {
                $lastError = $exception;
                $this->recordUsage($user, $provider, [
                    'usage' => [],
                    'model' => config("airmius_ai.providers.{$provider}.model"),
                ], $preparedImage, 'error', ['error' => Str::limit($exception->getMessage(), 120, '')]);
            }
        }

        throw new RuntimeException($lastError?->getMessage() ?: 'Kein KI-Anbieter ist konfiguriert.');
    }

    private function analyzeNutritionImageWithGoogle(array $image, array $context): array
    {
        $provider = config('airmius_ai.providers.google');
        $model = $provider['model'] ?? 'gemini-3.1-flash-lite';
        $url = rtrim((string) ($provider['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/')
            ."/v1beta/models/{$model}:generateContent";

        $response = Http::timeout((int) config('airmius_ai.timeout', 20))
            ->acceptJson()
            ->post($url.'?key='.$provider['api_key'], [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->nutritionImagePrompt($context)],
                        ['inline_data' => [
                            'mime_type' => $image['mime'],
                            'data' => $image['base64'],
                        ]],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'response_mime_type' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Google-KI konnte das Bild nicht analysieren.');
        }

        $payload = $response->json();
        $text = (string) data_get($payload, 'candidates.0.content.parts.0.text', '');

        return [
            'data' => $this->parseJsonObject($text),
            'usage' => [
                'input_tokens' => (int) data_get($payload, 'usageMetadata.promptTokenCount', 0),
                'output_tokens' => (int) data_get($payload, 'usageMetadata.candidatesTokenCount', 0),
            ],
            'model' => $model,
        ];
    }

    private function analyzeNutritionImageWithOpenAi(array $image, array $context): array
    {
        $provider = config('airmius_ai.providers.openai');
        $model = $provider['model'] ?? 'gpt-5.4-mini';
        $url = rtrim((string) ($provider['base_url'] ?? 'https://api.openai.com/v1'), '/').'/responses';

        $response = Http::timeout((int) config('airmius_ai.timeout', 20))
            ->withToken((string) ($provider['api_key'] ?? ''))
            ->acceptJson()
            ->post($url, [
                'model' => $model,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $this->nutritionImagePrompt($context)],
                        ['type' => 'input_image', 'image_url' => 'data:'.$image['mime'].';base64,'.$image['base64']],
                    ],
                ]],
                'max_output_tokens' => 900,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI konnte das Bild nicht analysieren.');
        }

        $payload = $response->json();
        $text = $this->openAiOutputText($payload);

        return [
            'data' => $this->parseJsonObject($text),
            'usage' => [
                'input_tokens' => (int) (data_get($payload, 'usage.input_tokens') ?? data_get($payload, 'usage.prompt_tokens', 0)),
                'output_tokens' => (int) (data_get($payload, 'usage.output_tokens') ?? data_get($payload, 'usage.completion_tokens', 0)),
            ],
            'model' => $model,
        ];
    }

    private function analyzeNutritionImageWithOpenAiCompatible(string $providerKey, array $image, array $context): array
    {
        $provider = config("airmius_ai.providers.{$providerKey}");
        $model = $provider['model'] ?? null;
        $url = rtrim((string) ($provider['base_url'] ?? ''), '/').'/chat/completions';

        $response = Http::timeout((int) config('airmius_ai.timeout', 20))
            ->withToken((string) ($provider['api_key'] ?? ''))
            ->acceptJson()
            ->post($url, [
                'model' => $model,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->nutritionImagePrompt($context)],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => 'data:'.$image['mime'].';base64,'.$image['base64'],
                        ]],
                    ],
                ]],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'max_completion_tokens' => 900,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(config("airmius_ai.providers.{$providerKey}.label", $providerKey).' konnte das Bild nicht analysieren.');
        }

        $payload = $response->json();
        $text = (string) data_get($payload, 'choices.0.message.content', '');

        return [
            'data' => $this->parseJsonObject($text),
            'usage' => [
                'input_tokens' => (int) data_get($payload, 'usage.prompt_tokens', 0),
                'output_tokens' => (int) data_get($payload, 'usage.completion_tokens', 0),
            ],
            'model' => $model,
        ];
    }

    private function nutritionImagePrompt(array $context): string
    {
        $mealType = $context['meal_type'] ?? 'unknown';
        $dietStyle = $context['diet_style'] ?? 'unknown';

        return <<<PROMPT
Du bist die Airmius-Ernaehrungsanalyse. Analysiere das Essensbild datensparsam.
Schaetze Lebensmittel und Portionen vorsichtig. Gib keine medizinische Beratung.
Wenn du unsicher bist, nutze niedrigere confidence und nenne Rueckfragen in warnings.
Kontext: meal_type={$mealType}, diet_style={$dietStyle}.
Antworte ausschliesslich als JSON mit diesem Schema:
{
  "title": "kurzer Mahlzeitname",
  "calories": 0,
  "protein_g": 0,
  "carbs_g": 0,
  "fat_g": 0,
  "fiber_g": 0,
  "sugar_g": 0,
  "water_ml": 0,
  "items": [{"name": "Lebensmittel", "amount": "geschaetzte Portion"}],
  "confidence": 0.0,
  "notes": "kurzer Hinweis, dass es eine Schaetzung ist",
  "warnings": ["kurze Rueckfrage oder Unsicherheit"]
}
PROMPT;
    }

    private function normalizeNutritionResult(array $data): array
    {
        $number = fn (string $key, int|float $max = 20000): float => max(0, min($max, (float) ($data[$key] ?? 0)));
        $items = collect($data['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['name'] ?? '')) !== '')
            ->map(fn ($item) => [
                'name' => Str::limit(trim((string) ($item['name'] ?? '')), 80, ''),
                'amount' => Str::limit(trim((string) ($item['amount'] ?? '')), 80, ''),
            ])
            ->take(12)
            ->values()
            ->all();

        return [
            'title' => Str::limit(trim((string) ($data['title'] ?? $data['meal_title'] ?? 'KI-Mahlzeit')), 80, ''),
            'calories' => (int) round($number('calories')),
            'protein_g' => round($number('protein_g', 500), 1),
            'carbs_g' => round($number('carbs_g', 800), 1),
            'fat_g' => round($number('fat_g', 500), 1),
            'fiber_g' => round($number('fiber_g', 200), 1),
            'sugar_g' => round($number('sugar_g', 500), 1),
            'water_ml' => (int) round($number('water_ml', 5000)),
            'items' => $items,
            'confidence' => max(0, min(1, (float) ($data['confidence'] ?? 0.5))),
            'notes' => Str::limit(trim((string) ($data['notes'] ?? 'KI-Schaetzung bitte pruefen.')), 240, ''),
            'warnings' => collect($data['warnings'] ?? [])
                ->map(fn ($warning) => Str::limit(trim((string) $warning), 160, ''))
                ->filter()
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function parseJsonObject(string $text): array
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException('KI-Antwort war kein gueltiges JSON.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('KI-Antwort konnte nicht verarbeitet werden.');
        }

        return $decoded;
    }

    private function openAiOutputText(array $payload): string
    {
        foreach ((array) ($payload['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return (string) $content['text'];
                }
            }
        }

        return (string) ($payload['output_text'] ?? '');
    }

    private function availableProviders(): array
    {
        return collect(config('airmius_ai.providers', []))
            ->filter(fn (array $provider, string $key) => $this->providerAvailable($key))
            ->map(fn (array $provider, string $key) => [
                'key' => $key,
                'label' => $provider['label'] ?? $key,
                'model' => $provider['model'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function providerAvailable(string $provider): bool
    {
        return filled(config("airmius_ai.providers.{$provider}.api_key"))
            && filled(config("airmius_ai.providers.{$provider}.model"));
    }

    private function recordUsage(User $user, string $provider, array $result, array $image, string $status, array $metadata = []): void
    {
        $usage = $result['usage'] ?? [];

        $this->usage->record(
            area: 'ai',
            provider: $provider,
            service: 'image',
            operation: 'nutrition_meal_image',
            user: $user,
            billableQuantity: 1,
            inputTokens: (int) ($usage['input_tokens'] ?? $this->estimatedImageInputTokens($image)),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            status: $status,
            metadata: [
                'model' => $result['model'] ?? null,
                'image_width' => $image['width'] ?? null,
                'image_height' => $image['height'] ?? null,
                'image_bytes' => $image['bytes'] ?? null,
                ...$metadata,
            ],
        );
    }

    private function estimatedImageInputTokens(array $image): int
    {
        return max(1000, (int) ceil(((int) ($image['bytes'] ?? 0)) / 768) + 500);
    }
}
