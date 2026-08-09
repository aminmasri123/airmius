<?php

namespace App\Support;

final class RolloutAcceptanceRegistry
{
    public const CONTRACT = 'staged-rollout.v1';

    public const STAGES = [0, 5, 25, 100];

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'stages' => self::STAGES,
            'assignment' => [
                'deterministic' => true,
                'algorithm' => 'hmac_sha256_modulo_100',
                'stores_assignment' => false,
                'sets_cookie' => false,
                'emits_actor_or_bucket' => false,
                'personal_data_in_evidence' => false,
            ],
            'precedence' => [
                'global_kill_switch',
                'feature_kill_switch',
                'full_rollout_short_circuit',
                'authorized_pilot_club_override',
                'stage_zero',
                'stage_assignment',
            ],
            'features' => [
                'club_operating_system' => [
                    'routes' => ['auth.club-cockpit.index'],
                    'owner' => 'Club Product / SRE',
                ],
                'coach_daily_control' => [
                    'routes' => [
                        'auth.trainer-cockpit.index',
                        'api.v1.dashboard.daily-flow',
                        'api.v1.trainer-cockpit.index',
                        'api.v1.trainer-cockpit.logs.feedback.store',
                    ],
                    'owner' => 'Training Product / SRE',
                ],
                'growth_workspaces' => [
                    'routes' => [
                        'auth.sponsor-workspace.index',
                        'auth.sponsor-workspace.profile.update',
                        'auth.recruiting-pipeline.index',
                        'auth.recruiting-pipeline.applications.update',
                        'auth.recruiting-pipeline.applications.chat',
                        'auth.recruiting-pipeline.applications.destroy',
                        'api.v1.sponsor-workspace.index',
                        'api.v1.sponsor-workspace.profile.update',
                        'api.v1.recruiting-pipeline.index',
                        'api.v1.recruiting-pipeline.applications.update',
                        'api.v1.recruiting-pipeline.applications.chat',
                        'api.v1.recruiting-pipeline.applications.destroy',
                    ],
                    'owner' => 'Growth Product / SRE',
                ],
                'admin_operations' => [
                    'routes' => ['admin.operations.index', 'admin.operations.data'],
                    'owner' => 'Platform Operations / SRE',
                ],
            ],
            'guest_safety' => [
                'policy' => 'Guest, authentication, privacy, legal, discovery, SEO, contact, marketplace, learning, recruiting, sponsor, and order-status surfaces are never actor-bucketed.',
                'route_prefixes' => ['guest.', 'api.v1.public.'],
                'shared_cache_preserved' => true,
                'tracking_added' => false,
            ],
            'progression' => [
                ['stage' => 5, 'minimum_observation_hours' => 24],
                ['stage' => 25, 'minimum_observation_hours' => 48],
                ['stage' => 100, 'minimum_observation_hours' => 72],
                'requires' => [
                    'no_open_critical_incident',
                    'authorization_and_tenant_isolation_pass',
                    'error_rate_and_latency_within_slo',
                    'support_and_product_signoff',
                    'rollback_rehearsed',
                ],
            ],
            'rollback' => [
                'mechanism' => 'global_or_feature_kill_switch',
                'requires_deploy' => false,
                'preserves_user_data' => true,
                'owner' => 'SRE / Product',
            ],
            'evidence' => [
                'template' => 'resources/release/staged_rollout_evidence.template.json',
                'external_gate' => 'staged_rollout',
                'stores_personal_data' => false,
                'stores_secrets' => false,
            ],
            'responsibility' => [
                'responsible' => 'SRE / Product',
                'accountable' => 'CTO',
                'consulted' => ['Security', 'Data Protection', 'Support', 'QA'],
                'informed' => ['Engineering', 'Customer Success', 'Management'],
            ],
        ];
    }
}
