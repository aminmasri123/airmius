<?php

namespace App\Support;

final class AirmiusRoleMatrix
{
    public const SPORTLER = 'sportler';

    public const TRAINER = 'trainer';

    public const VEREIN_ADMIN = 'verein_admin';

    public const ELTERNTEIL = 'elternteil';

    public const SPONSOR = 'sponsor';

    public const PLATTFORM_ADMIN = 'plattform_admin';

    public static function all(): array
    {
        return [
            self::SPORTLER => [
                'key' => self::SPORTLER,
                'label' => 'Sportler',
                'scope' => 'person',
                'platform_roles' => ['player', 'youth_player', 'minor_player', 'guest_player'],
                'club_roles' => ['member'],
                'team_roles' => [TeamRoles::PLAYER, TeamRoles::CAPTAIN],
                'capabilities' => [
                    'profile.manage',
                    'feed.use',
                    'chat.use',
                    'events.join',
                    'training.log',
                    'files.own',
                    'club.membership.request',
                ],
            ],
            self::TRAINER => [
                'key' => self::TRAINER,
                'label' => 'Trainer',
                'scope' => 'team',
                'platform_roles' => ['coach', 'assistant_coach', 'performance_coach', 'fitness_coach'],
                'club_roles' => ['trainer', 'member'],
                'team_roles' => [TeamRoles::COACH],
                'capabilities' => [
                    'team.view',
                    'team.members.coordinate',
                    'training.plan.manage',
                    'attendance.manage',
                    'events.manage',
                    'chat.team',
                    'feed.team.publish',
                ],
            ],
            self::VEREIN_ADMIN => [
                'key' => self::VEREIN_ADMIN,
                'label' => 'Verein-Admin',
                'scope' => 'club',
                'platform_roles' => ['club_owner', 'club_admin', 'club_manager', 'academy_manager', 'financial_controller', 'media_manager'],
                'club_roles' => ClubRoles::ELEVATED,
                'team_roles' => [TeamRoles::CLUB_PRESIDENT, TeamRoles::TREASURER, TeamRoles::COACH],
                'capabilities' => [
                    'club.profile.manage',
                    'club.members.manage',
                    'club.teams.manage',
                    'club.billing.manage',
                    'club.files.manage',
                    'club.events.manage',
                    'club.audit.view',
                ],
            ],
            self::ELTERNTEIL => [
                'key' => self::ELTERNTEIL,
                'label' => 'Elternteil',
                'scope' => 'guardian',
                'platform_roles' => ['parent', 'guardian'],
                'club_roles' => ['member'],
                'team_roles' => [TeamRoles::PARENT_CONTACT],
                'capabilities' => [
                    'guardian.children.view',
                    'guardian.consent.manage',
                    'guardian.notifications',
                    'events.child.respond',
                    'team.parent_contact',
                ],
            ],
            self::SPONSOR => [
                'key' => self::SPONSOR,
                'label' => 'Sponsor',
                'scope' => 'sponsor',
                'platform_roles' => ['sponsor', 'sponsor_manager'],
                'club_roles' => [],
                'team_roles' => [],
                'capabilities' => [
                    'sponsor.workspace.view',
                    'sponsor.profile.manage',
                    'sponsor.partnerships.view',
                    'sponsor.campaigns.manage',
                    'sponsor.outcomes.view',
                ],
            ],
            self::PLATTFORM_ADMIN => [
                'key' => self::PLATTFORM_ADMIN,
                'label' => 'Plattform-Admin',
                'scope' => 'platform',
                'platform_roles' => Roles::SYSTEM,
                'club_roles' => [],
                'team_roles' => [],
                'capabilities' => [
                    'platform.users.manage',
                    'platform.roles.manage',
                    'platform.moderation.manage',
                    'platform.billing.manage',
                    'platform.content.manage',
                    'platform.support',
                    'platform.audit.view',
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function forClient(): array
    {
        return array_values(self::all());
    }
}
