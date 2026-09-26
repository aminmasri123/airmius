<?php

namespace App\Support;

final class AiSafetyReadinessReport
{
    public const VERSION = '2026-09-26.ai-safety-readiness.v1';

    public static function make(): array
    {
        return [
            'version' => self::VERSION,
            'automated_controls' => [
                self::control('prompt_injection', true, [
                    'system_instructions_never_replayed_from_user_content',
                    'source_context_required_for_drafts',
                    'outputs_marked_as_draft_or_suggestion',
                ]),
                self::control('data_exfiltration', true, [
                    'sensitive_context_redaction',
                    'provider_payloads_without_api_keys',
                    'no_raw_personal_data_in_readiness_evidence',
                ]),
                self::control('cross_tenant_leakage', true, [
                    'club_tenant_scope_required',
                    'foreign_club_context_rejected',
                    'file_and_document_summaries_use_existing_authorization',
                ]),
                self::control('hallucination', true, [
                    'manual_review_required',
                    'citations_required_for_handbook_answers',
                    'no_answer_when_no_reliable_source',
                ]),
                self::control('cost_control', true, [
                    'per_feature_rate_limit',
                    'monthly_cost_limit_declared',
                    'provider_availability_filtering',
                ]),
                self::control('provider_outage', true, [
                    'disabled_provider_filtered',
                    'unconfigured_provider_filtered',
                    'fallback_provider_order_declared',
                ]),
            ],
            'external_gates' => [
                'privacy_review' => 'pending',
                'dpia_assessment' => 'pending',
                'provider_dpa_review' => 'pending',
                'production_prompt_red_team' => 'pending',
            ],
            'evidence_policy' => [
                'stores_prompts' => false,
                'stores_provider_secrets' => false,
                'stores_personal_data' => false,
                'local_tests_can_grant_privacy_approval' => false,
            ],
            'decision' => 'no_go_until_privacy_and_provider_gates_pass',
        ];
    }

    private static function control(string $key, bool $automated, array $evidence): array
    {
        return [
            'key' => $key,
            'automated' => $automated,
            'status' => $automated ? 'pass' : 'pending',
            'evidence' => $evidence,
        ];
    }
}
