<?php

namespace App\Services\Ai;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AiProviderTokenStatusService
{
    private const WARNING_DAYS = 7;

    public function all(): array
    {
        $activeProviders = $this->activeProviderKeys();

        return collect(config('airmius_ai.providers', []))
            ->map(fn (array $provider, string $key) => $this->forProvider($key, $provider, in_array($key, $activeProviders, true)))
            ->values()
            ->all();
    }

    public function alerts(): array
    {
        return collect($this->all())
            ->filter(fn (array $status) => in_array($status['severity'], ['danger', 'warning'], true))
            ->values()
            ->all();
    }

    public function forProvider(string $key, array $provider, bool $active = true): array
    {
        $apiKey = (string) ($provider['api_key'] ?? '');
        $expiresAt = $this->jwtExpiresAt($apiKey);
        $now = time();
        $secondsRemaining = $expiresAt ? $expiresAt - $now : null;
        $hasKey = filled($apiKey);
        $hasModel = filled($provider['model'] ?? null);
        $status = 'ok';
        $severity = 'success';
        $label = 'Bereit';
        $message = 'API-Key ist gesetzt. Ein Ablaufdatum ist im Key nicht erkennbar.';

        if (! $active) {
            $status = 'inactive';
            $severity = 'neutral';
            $label = 'Nicht aktiv';
            $message = 'Dieser Anbieter ist aktuell nicht als Primär- oder Fallback-Anbieter gesetzt.';
        } elseif (! $hasKey) {
            $status = 'missing_key';
            $severity = 'danger';
            $label = 'API-Key fehlt';
            $message = 'Ohne API-Key kann dieser Anbieter nicht genutzt werden.';
        } elseif (! $hasModel) {
            $status = 'missing_model';
            $severity = 'danger';
            $label = 'Modell fehlt';
            $message = 'Bitte ein Modell in der Umgebungskonfiguration setzen.';
        } elseif ($expiresAt !== null && $secondsRemaining <= 0) {
            $status = 'expired';
            $severity = 'danger';
            $label = 'Abgelaufen';
            $message = 'Der Token ist abgelaufen. Bitte einen neuen API-Key eintragen.';
        } elseif ($expiresAt !== null && $secondsRemaining <= self::WARNING_DAYS * 86400) {
            $status = 'expiring_soon';
            $severity = 'warning';
            $label = 'Läuft bald ab';
            $message = 'Der Token läuft bald ab. Bitte rechtzeitig erneuern.';
        } elseif ($expiresAt !== null) {
            $status = 'valid_until';
            $severity = 'success';
            $label = 'Gültig';
            $message = 'Der Token ist aktuell gültig.';
        }

        return [
            'key' => $key,
            'label' => (string) ($provider['label'] ?? Str::headline($key)),
            'model' => $provider['model'] ?? null,
            'active' => $active,
            'configured' => $hasKey && $hasModel,
            'has_api_key' => $hasKey,
            'has_model' => $hasModel,
            'status' => $status,
            'status_key' => $status,
            'message_key' => "ai_tokens.messages.{$status}",
            'status_label' => $label,
            'severity' => $severity,
            'message' => $message,
            'expires_at' => $expiresAt ? Carbon::createFromTimestamp($expiresAt)->toIso8601String() : null,
            'expires_at_human' => $expiresAt ? Carbon::createFromTimestamp($expiresAt)->format('d.m.Y H:i') : null,
            'seconds_remaining' => $secondsRemaining,
            'days_remaining' => $secondsRemaining !== null ? (int) floor($secondsRemaining / 86400) : null,
            'warning_days' => self::WARNING_DAYS,
        ];
    }

    public function jwtExpiresAt(string $token): ?int
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

    private function activeProviderKeys(): array
    {
        $features = config('airmius_ai.features', []);
        $providers = [
            config('airmius_ai.primary_provider'),
            config('airmius_ai.fallback_provider'),
        ];

        foreach ($features as $feature) {
            if (! (bool) ($feature['enabled'] ?? true)) {
                continue;
            }

            $providers[] = $feature['primary_provider'] ?? null;
            $providers[] = $feature['fallback_provider'] ?? null;
        }

        return collect($providers)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
