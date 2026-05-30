<?php

namespace App\Services\Ai;

use App\Models\ExternalProviderUsageEvent;
use App\Models\User;
use App\Services\ExternalProviderUsageService;
use App\Support\Roles;
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

    public function capabilities(?User $user = null): array
    {
        $providers = $this->availableProviders();
        $feature = config('airmius_ai.features.nutrition_image_analysis', []);
        $trainingFeature = config('airmius_ai.features.training_plan_generation', []);
        $nutritionImageEntitlement = $user ? $this->nutritionImageEntitlement($user) : [
            'allowed' => false,
            'reason' => 'KI-Bildanalyse ist eine Pro-Funktion.',
            'tier' => 'unknown',
            'label' => 'Unbekannt',
        ];
        $trainingEntitlement = $user ? $this->trainingPlanEntitlement($user) : [
            'allowed' => true,
            'reason' => null,
            'tier' => 'unknown',
            'label' => 'Unbekannt',
            'monthly_limit' => null,
            'monthly_used' => 0,
            'monthly_remaining' => null,
            'max_weeks' => 26,
        ];

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
                    && $providers !== []
                    && (bool) $nutritionImageEntitlement['allowed'],
                'primary_provider' => $feature['primary_provider'] ?? config('airmius_ai.primary_provider', 'google'),
                'fallback_provider' => $feature['fallback_provider'] ?? config('airmius_ai.fallback_provider', 'openai'),
                'tier' => $nutritionImageEntitlement['tier'],
                'tier_label' => $nutritionImageEntitlement['label'],
                'requires_premium' => ! (bool) $nutritionImageEntitlement['allowed'],
                'access_reason' => $nutritionImageEntitlement['reason'],
            ],
            'training_plan_generation' => [
                'enabled' => (bool) ($trainingFeature['enabled'] ?? true),
                'available' => (bool) config('airmius_ai.enabled', true)
                    && (bool) ($trainingFeature['enabled'] ?? true)
                    && $providers !== []
                    && (bool) $trainingEntitlement['allowed'],
                'primary_provider' => $trainingFeature['primary_provider'] ?? config('airmius_ai.primary_provider', 'ionos'),
                'fallback_provider' => $trainingFeature['fallback_provider'] ?? config('airmius_ai.fallback_provider', 'openai'),
                'max_items' => $this->trainingPlanMaxItems(),
                'tier' => $trainingEntitlement['tier'],
                'tier_label' => $trainingEntitlement['label'],
                'monthly_limit' => $trainingEntitlement['monthly_limit'],
                'monthly_used' => $trainingEntitlement['monthly_used'],
                'monthly_remaining' => $trainingEntitlement['monthly_remaining'],
                'max_weeks' => $trainingEntitlement['max_weeks'],
                'requires_premium' => in_array($trainingEntitlement['tier'], ['pro', 'trainer_club', 'trainer_club_locked'], true),
                'access_reason' => $trainingEntitlement['reason'],
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

        $entitlement = $this->nutritionImageEntitlement($user);

        if (! $entitlement['allowed']) {
            throw new RuntimeException($entitlement['reason'] ?: 'KI-Bildanalyse ist für dein Konto nicht freigeschaltet.');
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
                $lastError = $this->friendlyProviderException($provider, $exception, 'das Bild nicht analysieren');
                $this->recordUsage($user, $provider, [
                    'usage' => [],
                    'model' => config("airmius_ai.providers.{$provider}.model"),
                ], $preparedImage, 'error', ['error' => Str::limit($lastError->getMessage(), 120, '')]);
            }
        }

        throw new RuntimeException($lastError?->getMessage() ?: 'Kein KI-Anbieter ist konfiguriert.');
    }

    public function generateTrainingPlan(User $user, array $context = []): array
    {
        if (! (bool) config('airmius_ai.enabled', true)) {
            throw new RuntimeException('KI-Funktionen sind aktuell deaktiviert.');
        }

        $feature = config('airmius_ai.features.training_plan_generation', []);

        if (! (bool) ($feature['enabled'] ?? true)) {
            throw new RuntimeException('KI-Trainingspläne sind aktuell deaktiviert.');
        }

        $entitlement = $this->trainingPlanEntitlement($user);

        if (! $entitlement['allowed']) {
            throw new RuntimeException($entitlement['reason'] ?: 'KI-Trainingspläne sind für dein Konto nicht freigeschaltet.');
        }

        $requestedWeeks = max(1, (int) ($context['weeks'] ?? 4));

        if ($requestedWeeks > (int) $entitlement['max_weeks']) {
            throw new RuntimeException("Dein aktuelles KI-Kontingent erlaubt Trainingspläne bis {$entitlement['max_weeks']} Wochen. Für längere Pläne brauchst du die nächste Stufe.");
        }

        $this->extendExecutionTime($this->trainingPlanTimeout() + 20);

        $providers = array_values(array_unique(array_filter([
            $feature['primary_provider'] ?? config('airmius_ai.primary_provider', 'ionos'),
            $feature['fallback_provider'] ?? config('airmius_ai.fallback_provider', 'openai'),
        ])));
        $lastError = null;

        foreach ($providers as $provider) {
            if (! $this->providerAvailable($provider)) {
                continue;
            }

            try {
                $result = match ($provider) {
                    'google' => $this->generateTextWithGoogle($this->trainingPlanPrompt($context)),
                    'openai' => $this->generateTextWithOpenAi($this->trainingPlanPrompt($context)),
                    'ionos' => $this->generateTextWithOpenAiCompatible($provider, $this->trainingPlanPrompt($context)),
                    default => throw new RuntimeException('Unbekannter KI-Anbieter.'),
                };

                $this->recordTextUsage($user, $provider, $result, 'training_plan_generation', 'ok');

                return [
                    ...$this->normalizeTrainingPlanResult($result['data'] ?? [], $context),
                    'provider' => $provider,
                    'provider_label' => config("airmius_ai.providers.{$provider}.label", $provider),
                    'model' => $result['model'] ?? config("airmius_ai.providers.{$provider}.model"),
                    'needs_user_confirmation' => true,
                ];
            } catch (Throwable $exception) {
                $lastError = $this->friendlyProviderException($provider, $exception, 'den Trainingsplan nicht erstellen');
                $this->recordTextUsage($user, $provider, [
                    'usage' => [],
                    'model' => config("airmius_ai.providers.{$provider}.model"),
                ], 'training_plan_generation', 'error', ['error' => Str::limit($lastError->getMessage(), 120, '')]);
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
            ->connectTimeout($this->aiConnectTimeout())
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
            ->connectTimeout($this->aiConnectTimeout())
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
        $this->ensureProviderTokenIsUsable($providerKey, (string) ($provider['api_key'] ?? ''));

        $response = Http::timeout((int) config('airmius_ai.timeout', 20))
            ->connectTimeout($this->aiConnectTimeout())
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
                'max_tokens' => 900,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->providerFailureMessage($providerKey, 'das Bild nicht analysieren', $response));
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

    private function generateTextWithGoogle(string $prompt): array
    {
        $provider = config('airmius_ai.providers.google');
        $model = $provider['model'] ?? 'gemini-3.1-flash-lite';
        $url = rtrim((string) ($provider['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/')
            ."/v1beta/models/{$model}:generateContent";

        $response = Http::timeout($this->trainingPlanTimeout())
            ->connectTimeout($this->aiConnectTimeout())
            ->acceptJson()
            ->post($url.'?key='.$provider['api_key'], [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'response_mime_type' => 'application/json',
                    'maxOutputTokens' => $this->trainingPlanOutputTokens(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Google-KI konnte den Trainingsplan nicht erstellen.');
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

    private function generateTextWithOpenAi(string $prompt): array
    {
        $provider = config('airmius_ai.providers.openai');
        $model = $provider['model'] ?? 'gpt-5.4-mini';
        $url = rtrim((string) ($provider['base_url'] ?? 'https://api.openai.com/v1'), '/').'/responses';

        $response = Http::timeout($this->trainingPlanTimeout())
            ->connectTimeout($this->aiConnectTimeout())
            ->withToken((string) ($provider['api_key'] ?? ''))
            ->acceptJson()
            ->post($url, [
                'model' => $model,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                    ],
                ]],
                'max_output_tokens' => $this->trainingPlanOutputTokens(),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI konnte den Trainingsplan nicht erstellen.');
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

    private function generateTextWithOpenAiCompatible(string $providerKey, string $prompt): array
    {
        $provider = config("airmius_ai.providers.{$providerKey}");
        $model = $provider['model'] ?? null;
        $url = rtrim((string) ($provider['base_url'] ?? ''), '/').'/chat/completions';
        $this->ensureProviderTokenIsUsable($providerKey, (string) ($provider['api_key'] ?? ''));

        $response = Http::timeout($this->trainingPlanTimeout())
            ->connectTimeout($this->aiConnectTimeout())
            ->withToken((string) ($provider['api_key'] ?? ''))
            ->acceptJson()
            ->post($url, [
                'model' => $model,
                'messages' => [[
                    'role' => 'user',
                    'content' => $prompt,
                ]],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => $this->trainingPlanOutputTokens(),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->providerFailureMessage($providerKey, 'den Trainingsplan nicht erstellen', $response));
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
Du bist die Airmius-Ernährungsanalyse. Analysiere das Essensbild datensparsam.
Schätze Lebensmittel und Portionen vorsichtig. Gib keine medizinische Beratung.
Wenn du unsicher bist, nutze niedrigere confidence und nenne Rückfragen in warnings.
Kontext: meal_type={$mealType}, diet_style={$dietStyle}.
Antworte ausschließlich als JSON mit diesem Schema:
{
  "title": "kurzer Mahlzeitname",
  "calories": 0,
  "protein_g": 0,
  "carbs_g": 0,
  "fat_g": 0,
  "fiber_g": 0,
  "sugar_g": 0,
  "water_ml": 0,
  "items": [{"name": "Lebensmittel", "amount": "geschätzte Portion"}],
  "confidence": 0.0,
  "notes": "kurzer Hinweis, dass es eine Schätzung ist",
  "warnings": ["kurze Rückfrage oder Unsicherheit"]
}
PROMPT;
    }

    private function trainingPlanPrompt(array $context): string
    {
        $maxItems = $this->trainingPlanMaxItems();
        $requestedWeeks = max(1, min(26, (int) ($context['weeks'] ?? 4)));
        $requestedSessions = max(1, min(6, (int) ($context['sessions_per_week'] ?? 3)));
        $requestedItems = min($maxItems, $requestedWeeks * $requestedSessions);
        $payload = json_encode([
            'title' => $context['title'] ?? '',
            'goal' => $context['goal'] ?? '',
            'sport_type' => $context['sport_type'] ?? 'laufen',
            'training_type' => $context['training_type'] ?? '',
            'level' => $context['level'] ?? 'intermediate',
            'phase' => $context['phase'] ?? 'build',
            'weeks' => $context['weeks'] ?? 4,
            'sessions_per_week' => $context['sessions_per_week'] ?? 3,
            'duration_minutes' => $context['duration_minutes'] ?? null,
            'starts_on' => $context['starts_on'] ?? null,
            'equipment' => $context['equipment'] ?? '',
            'constraints' => $context['constraints'] ?? '',
            'preferences' => $context['preferences'] ?? '',
            'athlete_profile' => $context['athlete_profile'] ?? null,
            'profile_readiness' => $context['profile_readiness'] ?? null,
            'profile_estimate_mode' => (bool) ($context['profile_estimate_mode'] ?? false),
            'current_plan' => $context['current_plan'] ?? null,
            'revision_instruction' => $context['revision_instruction'] ?? '',
            'requested_items' => $requestedItems,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Du bist der Airmius Trainingsplan-Coach. Erstelle einen sportlich nachvollziehbaren Plan, aber gib keine medizinische Beratung.
Der Nutzer muss nach der KI-Vorschau selbst bestätigen. Plane deshalb so, dass jede Einheit später als normale Trainingseinheit editierbar ist.
Wenn ein bestehender current_plan und revision_instruction vorhanden sind, überarbeite den Plan danach statt neu anzufangen.
Nutze nur realistische Progression: Belastung langsam steigern, Erholung einbauen, keine sinnlosen Hin-und-zurück-Logiken.
Für 2-, 3- und 6-Monatspläne: plane in Phasen, aber gib trotzdem konkrete editierbare Einheiten aus.
Erstelle genau Wochen x Einheiten pro Woche, sofern diese Anzahl nicht höher als {$maxItems} ist. Für diese Anfrage sind {$requestedItems} Einheiten vorgesehen.
Halte jede Einheit kurz und konkret, damit lange Pläne speicherbar bleiben.
Unterscheide sauber: sport_type ist die echte Sportart (z. B. laufen, gym, schwimmen, fussball, cycling). training_type in den ausgegebenen items ist nur Methode/Schwerpunkt innerhalb dieser Sportart (z. B. long_run, run_interval, strength, hypertrophy).
Wenn die Nutzerdaten training_type "balanced" oder leer enthalten, kombiniere die sinnvollen Schwerpunkte automatisch ausgewogen. Bei Laufen z. B. Grundlage, Tempo/Intervalle, Technik/Stabilität und Regeneration; bei Gym z. B. Kraft, Hypertrophie, Mobility und Entlastung.
Bei Gym: keine Distanzfelder erzwingen; nutze Sätze, Wiederholungen, Gewicht kg als "anpassen", Pause und Tempo.
Bei Intervallen/Laufen/Rad/Schwimmen: Distanz, Wiederholungen, Pace/Tempo, Pausen und Intensität sinnvoll eintragen.
Pace-Regel für Laufen: Erfinde keine exakten min/km-Werte, wenn keine echte Referenz wie aktuelle 5-km-Zeit, 10-km-Zeit, VMA, Schwellentempo oder bekannte Zielpace in den Nutzerdaten steht. Nutze dann verständliche relative Angaben wie "locker im Sprechtempo", "5-km-Gefühl", "RPE 7-8", "Zone 2" oder "kontrolliert zügig". Verwechsle niemals km/h mit min/km: Werte wie 16-17 min/km sind für harte Laufintervalle unbrauchbar.
Wenn eine echte numerische Lauf-Pace vorhanden ist, gib in metrics zusätzlich "km/h" an. Beispiel: {"Pace": "5:00 min/km", "km/h": "12,0 km/h"}. Bei relativen Pace-Angaben keine km/h erfinden.
Nutze athlete_profile als harte Grundlage. Wenn dort Leistungsdaten, Verletzungen, verfügbare Tage oder Equipment stehen, muss der Plan darauf Rücksicht nehmen und darf keine stärkeren Annahmen treffen.
Wenn profile_estimate_mode true ist oder profile_readiness.ready false ist: erstelle nur einen konservativen, allgemeineren Plan. Keine aggressiven Umfangssprünge, keine exakten Pace-/Gewichtsziele ohne echte Referenz, keine Hochrisiko-Einheiten. Nenne in convincing_explanation und warnings klar, dass fehlende Daten geschätzt wurden und dass der Nutzer sein Sportprofil nachtragen sollte. Nutze relative Intensitäten wie locker, mittel, RPE, Zone oder Technikfokus statt erfundener Zahlen.
Antworte ausschließlich als JSON. Maximal {$maxItems} Einheiten.

Nutzerdaten:
{$payload}

JSON-Schema:
{
  "title": "kurzer Planname",
  "summary": "1-2 Sätze, was der Plan macht",
  "convincing_explanation": "warum genau dieser Aufbau für Ziel, Niveau und Zeit passt",
  "progression_logic": ["kurzer Grund 1", "kurzer Grund 2", "kurzer Grund 3"],
  "analysis_tips": ["was der Nutzer in der Analyse beobachten soll"],
  "adjustment_tips": ["wie der Nutzer den Plan anpassen kann"],
  "warnings": ["vorsichtige Hinweise, falls relevant"],
  "settings": {
    "goal": "Ziel",
    "phase": "base|build|peak|recovery|rehab",
    "level": "beginner|intermediate|advanced|elite",
    "weeks": 4,
    "weekly_sessions": 3
  },
  "items": [
    {
      "week": 1,
      "day": "Montag",
      "title": "Einheitstitel",
      "sport_type": "laufen|gym|schwimmen|fussball|cycling|yoga|training",
      "training_type": "long_run|run_interval|tempo_run|recovery_run|strength|hypertrophy|gym|mobility|swim|swim_interval|endurance_swim|football|football_conditioning|football_speed|cycling|bike_interval|hill_ride|generic",
      "focus": "Fokus",
      "description": "kurze Erklärung dieser Einheit",
      "duration_minutes": 45,
      "distance_km": 0,
      "intensity": "locker|mittel|hart|recovery",
      "load": "low|medium|high|test",
      "todos": ["Aufgabe 1", "Aufgabe 2"],
      "metrics": {"Sätze": "4", "Wiederholungen": "8-10", "Gewicht kg": "anpassen", "Pause": "90s"},
      "rationale": "warum diese Einheit im Plan ist"
    }
  ]
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
            'notes' => Str::limit(trim((string) ($data['notes'] ?? 'KI-Schätzung bitte prüfen.')), 240, ''),
            'warnings' => collect($data['warnings'] ?? [])
                ->map(fn ($warning) => Str::limit(trim((string) $warning), 160, ''))
                ->filter()
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function normalizeTrainingPlanResult(array $data, array $context): array
    {
        $maxItems = $this->trainingPlanMaxItems();
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $fallbackTitle = trim((string) ($context['title'] ?? 'KI-Trainingsplan')) ?: 'KI-Trainingsplan';
        $fallbackGoal = trim((string) ($context['goal'] ?? ''));

        $items = collect($data['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['title'] ?? '')) !== '')
            ->take($maxItems)
            ->values()
            ->map(function (array $item, int $index) use ($settings, $context) {
                $sportType = Str::limit(trim((string) ($item['sport_type'] ?? $context['sport_type'] ?? 'laufen')), 80, '');
                $trainingType = Str::limit(trim((string) ($item['training_type'] ?? $context['training_type'] ?? 'generic')), 80, '');
                $intensity = $this->enumValue((string) ($item['intensity'] ?? 'mittel'), ['locker', 'mittel', 'hart', 'recovery'], 'mittel');

                return [
                    'week' => max(1, min(104, (int) ($item['week'] ?? floor($index / max(1, (int) ($settings['weekly_sessions'] ?? $context['sessions_per_week'] ?? 3))) + 1))),
                    'day' => Str::limit(trim((string) ($item['day'] ?? '')), 24, ''),
                    'title' => Str::limit(trim((string) ($item['title'] ?? 'Trainingseinheit')), 160, ''),
                    'sport_type' => $sportType,
                    'training_type' => $trainingType,
                    'focus' => Str::limit(trim((string) ($item['focus'] ?? '')), 160, ''),
                    'description' => $this->sanitizeRunningPaceText(Str::limit(trim((string) ($item['description'] ?? $item['rationale'] ?? '')), 3000, ''), $sportType, $trainingType, $intensity),
                    'duration_minutes' => $this->boundedInt($item['duration_minutes'] ?? null, 0, 14400),
                    'distance_km' => $this->boundedFloat($item['distance_km'] ?? null, 0, 10000),
                    'calories' => $this->boundedInt($item['calories'] ?? null, 0, 200000),
                    'intensity' => $intensity,
                    'load' => $this->enumValue((string) ($item['load'] ?? 'medium'), ['low', 'medium', 'high', 'test'], 'medium'),
                    'todos' => $this->normalizeStringList($item['todos'] ?? []),
                    'metrics' => $this->normalizeMetrics($item['metrics'] ?? [], $sportType, $trainingType, $intensity),
                    'rationale' => $this->sanitizeRunningPaceText(Str::limit(trim((string) ($item['rationale'] ?? '')), 500, ''), $sportType, $trainingType, $intensity),
                ];
            })
            ->all();

        if ($items === []) {
            throw new RuntimeException('Die KI hat keine verwertbaren Trainingseinheiten erstellt.');
        }

        $weeks = max(1, min(104, (int) ($settings['weeks'] ?? $context['weeks'] ?? max(array_column($items, 'week')))));
        $weeklySessions = max(1, min(21, (int) ($settings['weekly_sessions'] ?? $context['sessions_per_week'] ?? 3)));

        return [
            'title' => Str::limit(trim((string) ($data['title'] ?? $fallbackTitle)), 160, ''),
            'summary' => Str::limit(trim((string) ($data['summary'] ?? 'KI-generierter Trainingsplan.')), 600, ''),
            'convincing_explanation' => Str::limit(trim((string) ($data['convincing_explanation'] ?? 'Der Plan baut Belastung schrittweise auf und lässt Raum für Anpassungen.')), 1200, ''),
            'progression_logic' => $this->normalizeStringList($data['progression_logic'] ?? []),
            'analysis_tips' => $this->normalizeStringList($data['analysis_tips'] ?? []),
            'adjustment_tips' => $this->normalizeStringList($data['adjustment_tips'] ?? []),
            'warnings' => $this->normalizeStringList($data['warnings'] ?? []),
            'settings' => [
                'goal' => Str::limit(trim((string) ($settings['goal'] ?? $fallbackGoal)), 200, ''),
                'phase' => $this->enumValue((string) ($settings['phase'] ?? $context['phase'] ?? 'build'), ['base', 'build', 'peak', 'recovery', 'rehab'], 'build'),
                'level' => $this->enumValue((string) ($settings['level'] ?? $context['level'] ?? 'intermediate'), ['beginner', 'intermediate', 'advanced', 'elite'], 'intermediate'),
                'weeks' => $weeks,
                'weekly_sessions' => $weeklySessions,
            ],
            'items' => $items,
        ];
    }

    private function normalizeStringList(mixed $value, int $limit = 8): array
    {
        $items = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);

        return collect($items)
            ->map(fn ($item) => Str::limit(trim((string) $item), 240, ''))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    private function trainingPlanMaxItems(): int
    {
        return max(1, min(156, (int) config('airmius_ai.features.training_plan_generation.max_items', 156)));
    }

    private function trainingPlanOutputTokens(): int
    {
        return max(3200, min(12000, (int) config('airmius_ai.features.training_plan_generation.output_tokens', 7000)));
    }

    private function trainingPlanTimeout(): int
    {
        return max(30, min(180, (int) config('airmius_ai.features.training_plan_generation.timeout', 90)));
    }

    private function aiConnectTimeout(): int
    {
        return max(3, min(30, (int) config('airmius_ai.connect_timeout', 10)));
    }

    private function extendExecutionTime(int $seconds): void
    {
        if (! function_exists('set_time_limit')) {
            return;
        }

        @set_time_limit(max(30, $seconds));
    }

    private function trainingPlanEntitlement(User $user): array
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return [
                'allowed' => true,
                'reason' => null,
                'tier' => 'admin',
                'label' => 'Admin',
                'monthly_limit' => null,
                'monthly_used' => $this->trainingPlanMonthlyUsage($user),
                'monthly_remaining' => null,
                'max_weeks' => 26,
            ];
        }

        $trainerOrClub = $user->hasAnyRole(array_merge(Roles::COACH, Roles::CLUB_ADMIN));
        $premiumTrainerOrClub = $this->hasPremiumTrainerOrClubPlan($user);

        if ($trainerOrClub && ! $premiumTrainerOrClub) {
            return [
                'allowed' => false,
                'reason' => 'KI-Trainingspläne für Trainer und Vereine sind nur mit Trainer/Verein Premium verfügbar.',
                'tier' => 'trainer_club_locked',
                'label' => 'Trainer/Verein',
                'monthly_limit' => 0,
                'monthly_used' => $this->trainingPlanMonthlyUsage($user),
                'monthly_remaining' => 0,
                'max_weeks' => 0,
            ];
        }

        $tier = $this->trainingPlanTier($user);
        $used = $this->trainingPlanMonthlyUsage($user);
        $remaining = $tier['monthly_limit'] === null ? null : max(0, $tier['monthly_limit'] - $used);

        return [
            'allowed' => $tier['monthly_limit'] === null || $remaining > 0,
            'reason' => $tier['monthly_limit'] !== null && $remaining <= 0
                ? "Dein monatliches KI-Trainingsplan-Limit ({$tier['monthly_limit']}x) ist erreicht. Es wird nächsten Monat automatisch zurückgesetzt."
                : null,
            'tier' => $tier['key'],
            'label' => $tier['label'],
            'monthly_limit' => $tier['monthly_limit'],
            'monthly_used' => $used,
            'monthly_remaining' => $remaining,
            'max_weeks' => $tier['max_weeks'],
        ];
    }

    private function nutritionImageEntitlement(User $user): array
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return [
                'allowed' => true,
                'reason' => null,
                'tier' => 'admin',
                'label' => 'Admin',
            ];
        }

        $slugs = $this->activeSubscriptionSlugs($user);

        if ($slugs->intersect(['sportler-pro', 'trainer-pro', 'club', 'pro', 'elite'])->isNotEmpty()) {
            return [
                'allowed' => true,
                'reason' => null,
                'tier' => 'pro',
                'label' => 'Pro',
            ];
        }

        return [
            'allowed' => false,
            'reason' => 'KI-Bildanalyse für Mahlzeiten ist in Sportler Pro, Trainer Pro oder einem Vereins-Pro-Plan enthalten.',
            'tier' => 'free',
            'label' => 'Free',
        ];
    }

    private function trainingPlanTier(User $user): array
    {
        $slugs = $this->activeSubscriptionSlugs($user);

        if ($user->hasAnyRole(array_merge(Roles::COACH, Roles::CLUB_ADMIN)) && $this->hasPremiumTrainerOrClubPlan($user)) {
            return [
                'key' => 'trainer_club',
                'label' => 'Trainer/Verein Premium',
                'monthly_limit' => 30,
                'max_weeks' => 26,
            ];
        }

        if ($slugs->intersect(['sportler-pro', 'trainer-pro', 'club', 'pro', 'elite'])->isNotEmpty()) {
            return [
                'key' => 'pro',
                'label' => 'Pro',
                'monthly_limit' => 10,
                'max_weeks' => 12,
            ];
        }

        return [
            'key' => 'free',
            'label' => 'Free',
            'monthly_limit' => 3,
            'max_weeks' => 4,
        ];
    }

    private function hasPremiumTrainerOrClubPlan(User $user): bool
    {
        return $this->activeSubscriptionSlugs($user)
            ->intersect(['trainer-pro', 'club', 'pro', 'elite'])
            ->isNotEmpty();
    }

    private function activeSubscriptionSlugs(User $user): \Illuminate\Support\Collection
    {
        $activeStatuses = ['active', 'trialing'];

        $userSlugs = $user->subscriptions()
            ->whereIn('status', $activeStatuses)
            ->with('plan:id,slug')
            ->get()
            ->pluck('plan.slug')
            ->filter();

        $clubSlugs = $user->clubs()
            ->with(['currentSubscription.plan:id,slug'])
            ->whereHas('currentSubscription', function ($query) use ($activeStatuses) {
                $query
                    ->whereIn('status', $activeStatuses)
                    ->whereHas('plan');
            })
            ->get()
            ->pluck('currentSubscription.plan.slug')
            ->filter();

        return $userSlugs->merge($clubSlugs)->unique()->values();
    }

    private function trainingPlanMonthlyUsage(User $user): int
    {
        return (int) ExternalProviderUsageEvent::query()
            ->where('user_id', $user->id)
            ->where('area', 'ai')
            ->where('service', 'text')
            ->where('operation', 'training_plan_generation')
            ->where('status', 'ok')
            ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    private function normalizeMetrics(mixed $value, ?string $sportType = null, ?string $trainingType = null, ?string $intensity = null): array
    {
        if (! is_array($value)) {
            return [];
        }

        $metrics = collect($value)
            ->mapWithKeys(function ($metricValue, $key) use ($sportType, $trainingType, $intensity) {
                $metricKey = Str::limit(trim((string) $key), 60, '');
                $metricText = is_array($metricValue) ? implode(', ', $metricValue) : trim((string) $metricValue);

                return [
                    $metricKey => Str::limit($this->sanitizeRunningPaceText($metricText, $sportType, $trainingType, $intensity), 140, ''),
                ];
            })
            ->filter(fn ($metricValue, $key) => $key !== '' && $metricValue !== '')
            ->take(10)
            ->all();

        return $this->appendRunningSpeedMetric($metrics, $sportType);
    }

    private function appendRunningSpeedMetric(array $metrics, ?string $sportType): array
    {
        if (! $this->isRunningSport($sportType) || $metrics === []) {
            return $metrics;
        }

        foreach ($metrics as $key => $metricValue) {
            $keyText = Str::lower((string) $key);
            $valueText = Str::lower((string) $metricValue);

            if (
                str_contains($keyText, 'km/h')
                || str_contains($keyText, 'geschwindigkeit')
                || str_contains($keyText, 'speed')
                || str_contains($valueText, 'km/h')
            ) {
                return $metrics;
            }
        }

        foreach ($metrics as $key => $metricValue) {
            $keyText = Str::lower((string) $key);
            $metricText = trim((string) $metricValue);

            if (
                ! str_contains($keyText, 'pace')
                && ! str_contains($keyText, 'tempo')
                && preg_match('/min\s*\/\s*km/i', $metricText) !== 1
            ) {
                continue;
            }

            $speedLabel = $this->runningSpeedLabelFromPace($metricText);

            if ($speedLabel === null) {
                continue;
            }

            if (count($metrics) >= 10) {
                $metrics = array_slice($metrics, 0, 9, true);
            }

            $metrics['km/h'] = $speedLabel;

            break;
        }

        return $metrics;
    }

    private function runningSpeedLabelFromPace(string $text): ?string
    {
        $text = trim($text);
        $hasUnit = preg_match('/min\s*\/\s*km/i', $text) === 1;
        $isUnitlessClockPace = preg_match('/^\s*\d{1,2}:\d{2}\s*(?:(?:-|–|bis)\s*\d{1,2}:\d{2})?\s*$/i', $text) === 1;

        if (! $hasUnit && ! $isUnitlessClockPace) {
            return null;
        }

        if (preg_match('/\b(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*(?:-|–|bis)\s*(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*(?:min\s*\/\s*km)?\b/i', $text, $matches) === 1) {
            $first = $this->parsePaceMinutes($matches[1] ?? '');
            $second = $this->parsePaceMinutes($matches[2] ?? '');

            if (! $this->isPlausibleRunningPaceMinutes($first) || ! $this->isPlausibleRunningPaceMinutes($second)) {
                return null;
            }

            return $this->formatKmhRange(60 / $first, 60 / $second);
        }

        if (preg_match('/\b(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*(?:min\s*\/\s*km)?\b/i', $text, $matches) === 1) {
            $minutes = $this->parsePaceMinutes($matches[1] ?? '');

            if (! $this->isPlausibleRunningPaceMinutes($minutes)) {
                return null;
            }

            return $this->formatKmh(60 / $minutes);
        }

        return null;
    }

    private function parsePaceMinutes(string $value): ?float
    {
        $value = trim(str_replace(',', '.', $value));

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches) === 1) {
            $seconds = (int) $matches[2];

            if ($seconds >= 60) {
                return null;
            }

            return (float) $matches[1] + ($seconds / 60);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function isPlausibleRunningPaceMinutes(?float $minutes): bool
    {
        return $minutes !== null && $minutes >= 2.0 && $minutes < 11.0;
    }

    private function formatKmhRange(float $firstSpeed, float $secondSpeed): string
    {
        $speeds = [$firstSpeed, $secondSpeed];
        sort($speeds);

        $first = round($speeds[0], 1);
        $second = round($speeds[1], 1);

        if (abs($first - $second) < 0.1) {
            return $this->formatKmh($first);
        }

        return number_format($first, 1, ',', '').'-'.number_format($second, 1, ',', '').' km/h';
    }

    private function formatKmh(float $speed): string
    {
        return number_format(round($speed, 1), 1, ',', '').' km/h';
    }

    private function sanitizeRunningPaceText(string $text, ?string $sportType, ?string $trainingType, ?string $intensity): string
    {
        if ($text === '' || ! $this->isRunningSport($sportType)) {
            return $text;
        }

        $text = preg_replace_callback(
            '/\b5\s*km-?Zielpace\s*\(([^)]*min\s*\/\s*km[^)]*)\)/i',
            fn (array $matches) => $this->containsImplausibleRunningPace($matches[1])
                ? $this->relativeRunningPaceLabel($trainingType, $intensity)
                : $matches[0],
            $text,
        ) ?? $text;

        return preg_replace_callback(
            '/\b(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*(?:-|–|bis)\s*(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*min\s*\/\s*km\b|\b(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*min\s*\/\s*km\b/i',
            function (array $matches) use ($trainingType, $intensity) {
                $firstRaw = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[3] ?? '');
                $secondRaw = ($matches[2] ?? '') !== '' ? $matches[2] : $firstRaw;
                $first = $this->parsePaceMinutes($firstRaw);
                $second = $this->parsePaceMinutes($secondRaw);

                if ($this->isPlausibleRunningPaceMinutes($first) && $this->isPlausibleRunningPaceMinutes($second)) {
                    return $matches[0];
                }

                return $this->relativeRunningPaceLabel($trainingType, $intensity);
            },
            $text,
        ) ?? $text;
    }

    private function containsImplausibleRunningPace(string $text): bool
    {
        preg_match_all(
            '/\b(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*(?:-|–|bis)\s*(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*min\s*\/\s*km\b|\b(\d{1,2}(?::\d{2}|[,.]\d+)?)\s*min\s*\/\s*km\b/i',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $firstRaw = ($match[1] ?? '') !== '' ? $match[1] : ($match[3] ?? '');
            $secondRaw = ($match[2] ?? '') !== '' ? $match[2] : $firstRaw;
            $first = $this->parsePaceMinutes($firstRaw);
            $second = $this->parsePaceMinutes($secondRaw);

            if (! $this->isPlausibleRunningPaceMinutes($first) || ! $this->isPlausibleRunningPaceMinutes($second)) {
                return true;
            }
        }

        return false;
    }

    private function relativeRunningPaceLabel(?string $trainingType, ?string $intensity): string
    {
        if (in_array($trainingType, ['run_interval'], true) || $intensity === 'hart') {
            return '5-km-Gefühl (RPE 7-8)';
        }

        if (in_array($trainingType, ['tempo_run'], true) || $intensity === 'mittel') {
            return 'kontrolliert zügig (RPE 6-7)';
        }

        return 'locker im Sprechtempo';
    }

    private function isRunningSport(?string $sportType): bool
    {
        $value = Str::lower(trim((string) $sportType));

        return in_array($value, ['laufen', 'running', 'run', 'joggen', 'jogging'], true);
    }

    private function boundedInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    private function boundedFloat(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round(max($min, min($max, (float) $value)), 2);
    }

    private function enumValue(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
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
            throw new RuntimeException('KI-Antwort war kein gültiges JSON.');
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

    private function ensureProviderTokenIsUsable(string $providerKey, string $apiKey): void
    {
        $expiresAt = $this->jwtExpiresAt($apiKey);

        if ($providerKey === 'ionos' && $expiresAt !== null && $expiresAt <= time()) {
            throw new RuntimeException('IONOS AI API-Key ist abgelaufen. Bitte erstelle im IONOS AI Model Hub einen neuen API-Key und aktualisiere IONOS_AI_API_KEY.');
        }
    }

    private function jwtExpiresAt(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) < 2) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode($payload, true);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && isset($decoded['exp']) ? (int) $decoded['exp'] : null;
    }

    private function providerFailureMessage(string $providerKey, string $action, mixed $response): string
    {
        $label = config("airmius_ai.providers.{$providerKey}.label", $providerKey);
        $message = "{$label} konnte {$action}.";
        $status = method_exists($response, 'status') ? (int) $response->status() : null;
        $details = $this->providerFailureDetails($response);

        if ($status) {
            $message .= " Status {$status}.";
        }

        if (in_array($status, [401, 403], true)) {
            $message .= ' Bitte API-Key, Berechtigungen und Modellzugriff prüfen.';
        }

        if ($details !== '') {
            $message .= ' Anbieter: '.$details;
        }

        return $message;
    }

    private function providerFailureDetails(mixed $response): string
    {
        try {
            $payload = method_exists($response, 'json') ? $response->json() : null;
        } catch (Throwable) {
            $payload = null;
        }

        $detail = is_array($payload)
            ? (data_get($payload, 'error.message') ?? data_get($payload, 'message') ?? data_get($payload, 'detail'))
            : null;

        if (is_array($detail)) {
            $detail = json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! is_string($detail) || trim($detail) === '') {
            $detail = method_exists($response, 'body') ? (string) $response->body() : '';
        }

        return Str::limit(trim(preg_replace('/\s+/', ' ', $detail)), 180, '');
    }

    private function friendlyProviderException(string $providerKey, Throwable $exception, string $action): RuntimeException
    {
        if (! $this->isTimeoutException($exception)) {
            return $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        $label = config("airmius_ai.providers.{$providerKey}.label", $providerKey);
        $timeout = str_contains($action, 'Trainingsplan')
            ? $this->trainingPlanTimeout()
            : (int) config('airmius_ai.timeout', 20);

        return new RuntimeException(
            "{$label} hat nach {$timeout} Sekunden noch keine Antwort geliefert. Bitte erneut versuchen oder einen kleineren Plan erstellen; falls es häufiger passiert, Timeout erhöhen oder einen Fallback-Anbieter konfigurieren.",
            0,
            $exception,
        );
    }

    private function isTimeoutException(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'cURL error 28')
            || str_contains($message, 'Operation timed out')
            || str_contains($message, 'timed out');
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

    private function recordTextUsage(User $user, string $provider, array $result, string $operation, string $status, array $metadata = []): void
    {
        $usage = $result['usage'] ?? [];

        $this->usage->record(
            area: 'ai',
            provider: $provider,
            service: 'text',
            operation: $operation,
            user: $user,
            billableQuantity: 1,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            status: $status,
            metadata: [
                'model' => $result['model'] ?? null,
                ...$metadata,
            ],
        );
    }

    private function estimatedImageInputTokens(array $image): int
    {
        return max(1000, (int) ceil(((int) ($image['bytes'] ?? 0)) / 768) + 500);
    }
}
