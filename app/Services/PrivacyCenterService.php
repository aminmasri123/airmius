<?php

namespace App\Services;

use App\Models\User;

final class PrivacyCenterService
{
    public const VERSION = '2026-08-09.privacy-center.v2';

    public function __construct(private readonly UserDataErasureService $dataErasure) {}

    /**
     * Build the small, account-scoped privacy projection used by native clients.
     * Provider tokens, scopes, remote identifiers and account e-mail addresses
     * intentionally never enter this contract.
     *
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        $providerProjection = $this->connectedProviders($user);

        return [
            'version' => self::VERSION,
            'user' => $user->only([
                'first_name',
                'last_name',
                'email',
                'country',
                'city',
                'postal_code',
            ]),
            'profile_address' => $user->only([
                'street',
                'house_number',
                'country',
                'postal_code',
                'city',
            ]),
            'privacy_settings' => [
                'default_post_visibility' => $user->default_post_visibility ?? 'public',
                'profile_visibility' => $user->profile_visibility ?? 'public',
                'direct_message_privacy' => $user->direct_message_privacy ?? 'everyone',
                'friend_request_privacy' => $user->friend_request_privacy ?? 'everyone',
                'ads_personalization_consent' => (bool) $user->ads_personalization_consent,
                'ads_measurement_consent' => (bool) $user->ads_measurement_consent,
                'product_analytics_consent' => (bool) $user->product_analytics_consent,
            ],
            'connected_providers' => $providerProjection,
            'data_erasure' => [
                'uses_social_login' => $providerProjection['summary']['login'] > 0,
                'account_email' => $user->email,
                'category_keys' => $this->dataErasure->categoryKeys(),
            ],
            'rights' => [
                'export' => '/api/v1/privacy/export',
                'correction' => '/api/v1/privacy/correction',
                'withdraw_consents' => '/api/v1/privacy/withdraw-consents',
                'partial_erasure' => '/api/v1/privacy/data-erasure',
            ],
        ];
    }

    /**
     * @return array{summary: array{total: int, login: int, sport: int}, items: array<int, array<string, mixed>>}
     */
    public function connectedProviders(User $user): array
    {
        $socialProviders = $user->socialAccounts()
            ->oldest('id')
            ->get(['id', 'provider', 'created_at'])
            ->map(fn ($account) => [
                'kind' => 'login',
                'provider' => (string) $account->provider,
                'status' => 'connected',
                'connected_at' => $account->created_at?->toJSON(),
                'last_synced_at' => null,
            ]);

        $sportProviders = $user->connectedSportAccounts()
            ->oldest('id')
            ->get(['id', 'provider', 'status', 'last_synced_at', 'created_at'])
            ->map(fn ($account) => [
                'kind' => 'sport',
                'provider' => (string) $account->provider,
                'status' => (string) ($account->status ?: 'connected'),
                'connected_at' => $account->created_at?->toJSON(),
                'last_synced_at' => $account->last_synced_at?->toJSON(),
            ]);

        $providers = $socialProviders->concat($sportProviders)->values();

        return [
            'summary' => [
                'total' => $providers->count(),
                'login' => $socialProviders->count(),
                'sport' => $sportProviders->count(),
            ],
            'items' => $providers->all(),
        ];
    }
}
