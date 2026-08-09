<?php

namespace App\Support;

final class CriticalJourneyRegistry
{
    public const CONTRACT = 'critical-journeys.v1';

    public static function definitions(): array
    {
        return [
            'athlete_day' => self::journey(
                contract: 'athlete-day.v1',
                personas: [AirmiusRoleMatrix::SPORTLER],
                responsible: 'Sport OS Team',
                accountable: 'Product Lead Sport',
                steps: [
                    self::step('today', 'training', 'auth.training.index', 'api/v1/dashboard/daily-flow'),
                    self::step('plan', 'training', 'auth.training.index', 'api/v1/training/plans'),
                    self::step('route', 'routes', 'auth.sport-map.index', 'api/v1/sport-routes'),
                    self::step('document', 'training', 'auth.training.logs.create', 'api/v1/training/logs'),
                    self::step('recover', 'nutrition', 'auth.nutrition.index', 'api/v1/nutrition'),
                    self::step('progress', 'leveling', 'auth.badges.index', 'api/v1/badges'),
                ],
                privacyBoundaries: [
                    'health_notes_private',
                    'explicit_training_visibility',
                    'location_data_owner_scoped',
                ],
            ),
            'coach_week' => self::journey(
                contract: 'coach-week.v1',
                personas: [AirmiusRoleMatrix::TRAINER],
                responsible: 'Sport OS Team',
                accountable: 'Backend Lead',
                steps: [
                    self::step('cockpit', 'training', 'auth.trainer-cockpit.index', 'api/v1/trainer-cockpit'),
                    self::step('availability', 'training', 'auth.training.index', 'api/v1/training/availability'),
                    self::step('team', 'teams', 'auth.teams.index', 'api/v1/teams'),
                    self::step('calendar', 'events', 'auth.events.index', 'api/v1/events'),
                    self::step('feedback', 'training', 'auth.training.index', 'api/v1/training/logs'),
                    self::step('message', 'chat', 'auth.conversations.index', 'api/v1/chat/conversations'),
                ],
                privacyBoundaries: [
                    'managed_team_scope',
                    'private_logs_excluded',
                    'guardian_contacts_excluded',
                ],
            ),
            'member_lifecycle' => self::journey(
                contract: 'member-lifecycle.v1',
                personas: [AirmiusRoleMatrix::SPORTLER, AirmiusRoleMatrix::VEREIN_ADMIN, AirmiusRoleMatrix::ELTERNTEIL],
                responsible: 'Organization Team',
                accountable: 'Product Lead Club',
                steps: [
                    self::step('discover', 'members', 'guest.vereine', 'api/v1/public/clubs'),
                    self::step('apply', 'members', 'guest.clubs.show', 'api/v1/membership-applications'),
                    self::step('manage', 'members', 'auth.club-memberships.index', 'api/v1/clubs'),
                    self::step('assign_team', 'teams', 'auth.teams.index', 'api/v1/teams'),
                    self::step('documents', 'files', 'auth.files.index', 'api/v1/files'),
                ],
                privacyBoundaries: [
                    'club_tenant_scope',
                    'guardian_consent_required',
                    'finance_role_separated',
                    'lifecycle_audited',
                ],
            ),
            'sponsor_measurement' => self::journey(
                contract: 'growth-workspace.v1',
                personas: [AirmiusRoleMatrix::SPONSOR],
                responsible: 'Growth Team',
                accountable: 'Revenue Lead',
                steps: [
                    self::step('public_profile', 'sponsors', 'guest.sponsors', 'api/v1/public/sponsors'),
                    self::step('brief', 'agency', 'guest.werbeagentur', 'api/v1/public/agency/requests'),
                    self::step('partnership', 'sponsors', 'auth.sponsor-workspace.index', 'api/v1/sponsor-workspace'),
                    self::step('campaign', 'ads', 'auth.commerce.index', 'api/v1/commerce/seller/campaigns'),
                    self::step('outcome', 'sponsors', 'auth.sponsor-workspace.index', 'api/v1/sponsor-workspace'),
                ],
                privacyBoundaries: [
                    'sponsor_owner_scope',
                    'agency_contacts_excluded',
                    'aggregated_outcomes_only',
                    'health_and_location_data_excluded',
                ],
            ),
            'recruiting_to_team' => self::journey(
                contract: 'recruiting-opportunity.v1',
                personas: [AirmiusRoleMatrix::SPORTLER, AirmiusRoleMatrix::VEREIN_ADMIN],
                responsible: 'Organization Team',
                accountable: 'Product Lead Club',
                steps: [
                    self::step('discover', 'recruiting', 'guest.jobs', 'api/v1/public/recruiting/jobs'),
                    self::step('share_profile', 'sport_matching', 'auth.users.show', 'api/v1/users/me/sport-cv'),
                    self::step('pipeline', 'recruiting', 'auth.recruiting-pipeline.index', 'api/v1/recruiting-pipeline'),
                    self::step('conversation', 'chat', 'auth.conversations.index', 'api/v1/chat/conversations'),
                    self::step('membership_handoff', 'members', 'auth.club-memberships.index', 'api/v1/membership-applications'),
                ],
                privacyBoundaries: [
                    'field_level_profile_consent',
                    'assistive_matching_only',
                    'separate_chat_consent',
                    'profile_share_revocable',
                ],
            ),
        ];
    }

    public static function forClient(): array
    {
        return [
            'contract' => self::CONTRACT,
            'items' => collect(self::definitions())
                ->map(fn (array $journey, string $key) => [
                    'key' => $key,
                    'contract' => $journey['contract'],
                    'personas' => $journey['personas'],
                    'modules' => collect($journey['steps'])->pluck('module')->unique()->values()->all(),
                    'steps' => collect($journey['steps'])
                        ->map(fn (array $step) => [
                            'key' => $step['key'],
                            'module' => $step['module'],
                            'api_path' => $step['api_path'],
                        ])
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private static function journey(
        string $contract,
        array $personas,
        string $responsible,
        string $accountable,
        array $steps,
        array $privacyBoundaries,
    ): array {
        return [
            'contract' => $contract,
            'personas' => $personas,
            'responsible' => $responsible,
            'accountable' => $accountable,
            'steps' => $steps,
            'privacy_boundaries' => $privacyBoundaries,
        ];
    }

    private static function step(string $key, string $module, string $webRoute, string $apiPath): array
    {
        return [
            'key' => $key,
            'module' => $module,
            'web_route' => $webRoute,
            'api_path' => $apiPath,
        ];
    }
}
