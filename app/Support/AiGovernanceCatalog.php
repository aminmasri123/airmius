<?php

namespace App\Support;

final class AiGovernanceCatalog
{
    public const VERSION = '2026-09-26.ai-governance.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'default_provider' => (string) config('airmius_ai.primary_provider', 'ionos'),
            'fallback_provider' => (string) config('airmius_ai.fallback_provider', 'openai'),
            'providers' => collect(config('airmius_ai.providers', []))
                ->map(fn (array $provider, string $key): array => [
                    'key' => $key,
                    'label' => $provider['label'] ?? $key,
                    'region' => $provider['region'] ?? 'external',
                    'enabled' => (bool) ($provider['enabled'] ?? false),
                    'model_configured' => filled($provider['model'] ?? null),
                    'stores_api_key' => false,
                ])
                ->values()
                ->all(),
            'controls' => [
                'requires_feature_opt_in' => true,
                'requires_request_logging' => true,
                'logs_prompts_without_secrets' => true,
                'redacts_sensitive_fields' => config('airmius_ai.gateway.redacted_keys', []),
                'monthly_cost_limit_cents' => (int) config('airmius_ai.governance.monthly_cost_limit_cents', 5000),
                'human_responsibility' => 'AI output is assistive; accountable users must review before publication, booking, scheduling, or operational action.',
            ],
            'features' => [
                self::feature('nutrition_image_analysis', 'food_estimation', ['health_context', 'user_uploaded_image'], 'suggestion_only'),
                self::feature('training_plan_generation', 'training_ideas', ['training_history', 'preferences'], 'suggestion_only'),
                self::feature('communication_drafts', 'communication_draft', ['club_context', 'message_intent'], 'draft_only'),
                self::feature('receipt_booking_suggestions', 'receipt_extraction_and_booking_suggestion', ['receipt_image_or_pdf', 'finance_metadata'], 'manual_confirmation_required'),
                self::feature('operations_suggestions', 'schedule_resource_data_quality_training_suggestions', ['calendar_metadata', 'resource_metadata', 'quality_markers', 'training_context'], 'explainable_non_executing_suggestions'),
            ],
        ];
    }

    private static function feature(string $key, string $purpose, array $dataClasses, string $executionMode): array
    {
        return [
            'key' => $key,
            'enabled' => (bool) data_get(config('airmius_ai.features', []), "{$key}.enabled", true),
            'purpose' => $purpose,
            'data_classes' => $dataClasses,
            'requires_opt_in' => true,
            'requires_human_confirmation' => true,
            'execution_mode' => $executionMode,
            'can_self_execute' => false,
            'explains_confidence' => true,
            'audit_event' => "ai.{$key}.suggested",
        ];
    }
}
