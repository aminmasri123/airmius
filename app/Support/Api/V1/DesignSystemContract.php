<?php

namespace App\Support\Api\V1;

use App\Support\LocalizationReadinessReport;

class DesignSystemContract
{
    public const CONTRACT_VERSION = '2026-06-03';

    public static function payload(): array
    {
        $tokens = self::tokens();

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'tokens_version' => (string) ($tokens['version'] ?? 'unknown'),
            'tokens_digest' => self::digest($tokens),
            'tokens' => $tokens,
            'mobile' => self::mobileContract($tokens),
            'localization' => LocalizationReadinessReport::make(),
        ];
    }

    public static function summary(): array
    {
        $tokens = self::tokens();

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'tokens_endpoint' => '/api/v1/design-system',
            'tokens_version' => (string) ($tokens['version'] ?? 'unknown'),
            'tokens_digest' => self::digest($tokens),
            'rtl_locales' => ['ar'],
            'supported_locales' => ['de', 'en', 'fr', 'ar'],
            'localization_readiness' => [
                'version' => LocalizationReadinessReport::VERSION,
                'audit_command' => 'airmius:i18n-audit --json',
                'endpoint' => '/api/v1/design-system',
                'report_path' => 'data.localization',
            ],
            'theme_keys' => array_keys($tokens['themes'] ?? []),
            'layout' => self::mobileContract($tokens)['layout'],
        ];
    }

    protected static function tokens(): array
    {
        $path = resource_path('design-tokens.json');

        if (! is_file($path)) {
            return [
                'version' => 'missing',
                'radius' => [],
                'spacing' => [],
                'typography' => [],
                'themes' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function mobileContract(array $tokens): array
    {
        return [
            'layout' => [
                'navigation' => 'bottom_tabs',
                'minimum_touch_target_px' => 44,
                'safe_area_required' => true,
                'supports_rtl_mirroring' => true,
                'compact_page_header' => true,
            ],
            'typography' => [
                'font_family' => data_get($tokens, 'typography.fontFamily', 'Figtree'),
                'dynamic_type' => [
                    'enabled' => true,
                    'min_scale' => 0.9,
                    'max_scale' => 1.25,
                ],
            ],
            'density' => [
                'card_radius_px' => (int) data_get($tokens, 'radius.md', 8),
                'control_radius_px' => (int) data_get($tokens, 'radius.sm', 6),
                'screen_padding_px' => (int) data_get($tokens, 'spacing.lg', 16),
                'section_gap_px' => (int) data_get($tokens, 'spacing.xl', 24),
            ],
            'accessibility' => [
                'contrast_required' => true,
                'focus_visible_required' => true,
                'reduce_motion_supported' => true,
            ],
        ];
    }

    protected static function digest(array $tokens): string
    {
        return hash('sha256', json_encode($tokens, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }
}
