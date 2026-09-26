<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class AiGatewayService
{
    public function providersForFeature(string $featureKey): array
    {
        $feature = config("airmius_ai.features.{$featureKey}", []);

        return collect([
            $feature['primary_provider'] ?? config('airmius_ai.primary_provider'),
            $feature['fallback_provider'] ?? config('airmius_ai.fallback_provider'),
        ])
            ->filter()
            ->unique()
            ->filter(fn (string $provider): bool => $this->providerAvailable($provider))
            ->values()
            ->all();
    }

    public function providerAvailable(string $provider): bool
    {
        return (bool) config("airmius_ai.providers.{$provider}.enabled", true)
            && filled(config("airmius_ai.providers.{$provider}.api_key"))
            && filled(config("airmius_ai.providers.{$provider}.model"));
    }

    public function beforeAttempt(User $user, string $featureKey, ?int $tenantId = null): void
    {
        $key = $this->rateLimitKey($user, $featureKey, $tenantId);
        $maxAttempts = max(1, (int) config('airmius_ai.rate_limit.max_attempts', 12));
        $decaySeconds = max(60, (int) config('airmius_ai.rate_limit.decay_seconds', 3600));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = max(1, RateLimiter::availableIn($key));

            throw new RuntimeException("KI-Rate-Limit erreicht. Bitte in {$seconds} Sekunden erneut versuchen.");
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    public function context(User $user, string $featureKey, ?int $tenantId = null): array
    {
        return [
            'gateway_version' => (string) config('airmius_ai.gateway.version', 'ai-gateway.v1'),
            'prompt_version' => (string) config("airmius_ai.features.{$featureKey}.prompt_version", $featureKey.'.v1'),
            'tenant_scope' => $this->tenantScope($user, $tenantId),
            'feature' => $featureKey,
        ];
    }

    public function redactContext(array $context): array
    {
        return $this->redactValue($context);
    }

    public function redactText(string $text): string
    {
        $redacted = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $text) ?? $text;
        $redacted = preg_replace('/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/i', '[redacted-iban]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\+?\d[\d\s().\/-]{7,}\d/', '[redacted-phone]', $redacted) ?? $redacted;

        return Str::limit($redacted, max(200, (int) config('airmius_ai.gateway.max_text_chars', 12000)), '');
    }

    private function tenantScope(User $user, ?int $tenantId): array
    {
        $allowedClubIds = $user->clubs()->pluck('clubs.id')->map(fn ($id) => (int) $id)->all();

        if ($tenantId !== null && ! in_array($tenantId, $allowedClubIds, true)) {
            throw new RuntimeException('KI-Mandantenscope ist für diesen Nutzer nicht freigegeben.');
        }

        return [
            'user_id' => (int) $user->id,
            'club_id' => $tenantId,
            'allowed_club_ids' => $allowedClubIds,
        ];
    }

    private function rateLimitKey(User $user, string $featureKey, ?int $tenantId): string
    {
        $scope = $tenantId !== null ? 'club:'.$tenantId : 'user:'.$user->id;

        return 'ai-gateway:'.$featureKey.':'.$scope;
    }

    private function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redactText($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $sensitiveKeys = config('airmius_ai.gateway.redacted_keys', [
            'email',
            'phone',
            'mobile',
            'address',
            'street',
            'iban',
            'bic',
            'account_holder',
            'birthdate',
        ]);

        return collect($value)
            ->mapWithKeys(function (mixed $item, string|int $key) use ($sensitiveKeys): array {
                $normalizedKey = Str::of((string) $key)->lower()->replace(['-', ' '], '_')->toString();

                if (in_array($normalizedKey, $sensitiveKeys, true)) {
                    return [$key => '[redacted]'];
                }

                return [$key => $this->redactValue($item)];
            })
            ->all();
    }
}
