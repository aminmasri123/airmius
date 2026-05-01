<?php
namespace App\Support;

class Roles
{
    public const FULL_ACCESS = [
        'super_admin',
        'admin',
        'system_admin',
    ];

    // 👑 SYSTEM (GLOBAL)
    public const SYSTEM = [
        'super_admin',      // Globaler Systemadministrator mit Vollzugriff
        'admin',            // System Administrator auf Plattform Ebene
        'system_admin',     // Technischer System Admin
        'support',          // Support Mitarbeiter für Tickets & Hilfe
        'redaktor',         // Website Redaktion für Blog und Inhalte
    ];

    // 🏢 CLUB MANAGEMENT
    public const CLUB_ADMIN = [
        'club_owner',       // Besitzer eines Clubs
        'club_admin',       // Verwaltet einen Club vollständig
        'club_manager',     // Operativer Manager eines Clubs
        'academy_manager',  // Leitet Akademie
        'financial_controller', // Finanz Kontrolle
        'media_manager',    // Medien & Content
    ];

    // ⚽ TEAM & SPORT
    public const COACH = [
        'coach',            // Trainer
        'assistant_coach',  // Assistent Trainer
        'performance_coach', // Performance Coach
        'fitness_coach',    // Fitness Trainer
        'team_manager',     // Team Organisation
        'captain',          // Team Kapitän
    ];

    // 👤 PLAYER
    public const PLAYER = [
        'player',           // Spieler
        'youth_player',     // Jugendspieler
        'minor_pending_consent', // Minderjaehriger Account wartet auf Zustimmung
        'minor_player',     // Minderjaehriger Spieler mit Zustimmung
        'guest_player',     // Gastspieler
    ];

    // 👪 COMMUNITY & FAMILY & OTHER
    public const PARENT = [
        'parent',           // Elternteil
        'guardian',         // Erziehungsberechtigter
    ];

    // 👀 PASSIVE
    public const PASSIVE = [
        'viewer',           // Nur Ansicht Zugriff
        'guest',            // Gast Benutzer
        'data_analyst',     // Analysiert Leistungsdaten
        'physiotherapist',  // Physiotherapeut
    ];

    // Alle Rollen als flache Liste
    public static function all(): array
    {
        return array_merge(
            self::SYSTEM,
            self::CLUB_ADMIN,
            self::COACH,
            self::PLAYER,
            self::PARENT,
            self::PASSIVE
        );
    }

    // Rollen mit Beschreibungen
    public static function withDescriptions(): array
    {
        return [
            // 👑 SYSTEM (GLOBAL)
            ['name' => 'super_admin', 'description' => 'Globaler Systemadministrator mit Vollzugriff'],
            ['name' => 'admin', 'description' => 'System Administrator auf Plattform Ebene'],
            ['name' => 'system_admin', 'description' => 'Technischer System Admin'],
            ['name' => 'support', 'description' => 'Support Mitarbeiter für Tickets & Hilfe'],
            ['name' => 'redaktor', 'description' => 'Website Redaktion für Blog und Inhalte'],

            // 🏢 CLUB MANAGEMENT
            ['name' => 'club_owner', 'description' => 'Besitzer eines Clubs'],
            ['name' => 'club_admin', 'description' => 'Verwaltet einen Club vollständig'],
            ['name' => 'club_manager', 'description' => 'Operativer Manager eines Clubs'],
            ['name' => 'academy_manager', 'description' => 'Leitet Akademie'],
            ['name' => 'financial_controller', 'description' => 'Finanz Kontrolle'],
            ['name' => 'media_manager', 'description' => 'Medien & Content'],

            // ⚽ TEAM & SPORT
            ['name' => 'coach', 'description' => 'Trainer'],
            ['name' => 'assistant_coach', 'description' => 'Assistent Trainer'],
            ['name' => 'performance_coach', 'description' => 'Performance Coach'],
            ['name' => 'fitness_coach', 'description' => 'Fitness Trainer'],
            ['name' => 'team_manager', 'description' => 'Team Organisation'],
            ['name' => 'captain', 'description' => 'Team Kapitän'],

            // 👤 PLAYER
            ['name' => 'player', 'description' => 'Spieler'],
            ['name' => 'youth_player', 'description' => 'Jugendspieler'],
            ['name' => 'minor_pending_consent', 'description' => 'Minderjaehriger Account wartet auf Zustimmung der Erziehungsberechtigten'],
            ['name' => 'minor_player', 'description' => 'Minderjaehriger Spieler mit Zustimmung der Erziehungsberechtigten'],
            ['name' => 'guest_player', 'description' => 'Gastspieler'],

            // 👪 COMMUNITY & FAMILY & OTHER
            ['name' => 'parent', 'description' => 'Elternteil'],
            ['name' => 'guardian', 'description' => 'Erziehungsberechtigter'],

            // 👀 PASSIVE
            ['name' => 'viewer', 'description' => 'Nur Ansicht Zugriff'],
            ['name' => 'guest', 'description' => 'Gast Benutzer'],
            ['name' => 'data_analyst', 'description' => 'Analysiert Leistungsdaten'],
            ['name' => 'physiotherapist', 'description' => 'Physiotherapeut'],
        ];
    }
}
