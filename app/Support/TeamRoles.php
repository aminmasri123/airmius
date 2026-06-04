<?php

namespace App\Support;

class TeamRoles
{
    public const COACH = 'Coach';
    public const CAPTAIN = 'Captain';
    public const PLAYER = 'Player';
    public const TREASURER = 'Treasurer';
    public const CLUB_PRESIDENT = 'ClubPresident';
    public const PARENT_CONTACT = 'ParentContact';

    public const TEAM_STAFF_ROLES = [
        self::COACH,
        self::CAPTAIN,
        self::TREASURER,
        self::CLUB_PRESIDENT,
    ];

    public const PROFILE_DEFINITIONS = [
        self::COACH => [
            'label' => 'Trainer',
            'description' => 'Leitet Trainingseinheiten und Inhalte, stellt Spielaufstellungen zusammen.',
            'group' => 'team_management',
            'is_lead_role' => true,
        ],
        self::CAPTAIN => [
            'label' => 'Kapitän',
            'description' => 'Verbindet Team und Trainer auf dem Platz.',
            'group' => 'team_management',
            'is_lead_role' => true,
        ],
        self::PLAYER => [
            'label' => 'Spieler',
            'description' => 'Teilnehmerrolle für Mannschaftsmitglieder.',
            'group' => 'team_member',
            'is_lead_role' => false,
        ],
        self::TREASURER => [
            'label' => 'Kassenwart',
            'description' => 'Verwaltet Gebühren, Teamkasse und kleine Finanzvorgänge.',
            'group' => 'team_operations',
            'is_lead_role' => true,
        ],
        self::CLUB_PRESIDENT => [
            'label' => 'Vorsitzender',
            'description' => 'Organisation, Kontaktpersonen und Teamstruktur auf Clubebene.',
            'group' => 'team_operations',
            'is_lead_role' => true,
        ],
        self::PARENT_CONTACT => [
            'label' => 'Elternkontakt',
            'description' => 'Knotenpunkt für Elterngespräche und Kommunikation.',
            'group' => 'communication',
            'is_lead_role' => false,
        ],
    ];

    public static function all(): array
    {
        return [
            self::COACH,
            self::CAPTAIN,
            self::PLAYER,
            self::TREASURER,
            self::CLUB_PRESIDENT,
            self::PARENT_CONTACT,
        ];
    }

    public static function leadRoles(): array
    {
        return array_keys(array_filter(self::PROFILE_DEFINITIONS, fn ($entry) => $entry['is_lead_role'] ?? false));
    }

    public static function definition(string $role): array
    {
        return self::PROFILE_DEFINITIONS[$role] ?? [
            'label' => $role,
            'description' => 'Teamrolle',
            'group' => 'team_member',
            'is_lead_role' => false,
        ];
    }
}
