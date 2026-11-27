<?php

namespace App\Support;

final class ClubRoleTemplates
{
    public const DEFINITIONS = [
        'club_administrator' => ['name' => 'Vereinsadministrator', 'permissions' => ClubPermissions::ALL],
        'board' => ['name' => 'Vorstand', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::FINANCE_VIEW, ClubPermissions::CONTENT_MANAGE,
            ClubPermissions::EVENTS_MANAGE, ClubPermissions::FILES_MANAGE, ClubPermissions::SURVEYS_MANAGE,
            ClubPermissions::ANNOUNCEMENTS_MANAGE, ClubPermissions::SUPPORT_MANAGE, ClubPermissions::INVENTORY_VIEW,
        ]],
        'office' => ['name' => 'Geschäftsstelle', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::MEMBERS_MANAGE, ClubPermissions::CONTENT_MANAGE,
            ClubPermissions::EVENTS_MANAGE, ClubPermissions::FILES_MANAGE, ClubPermissions::SURVEYS_MANAGE,
            ClubPermissions::ANNOUNCEMENTS_MANAGE, ClubPermissions::SUPPORT_MANAGE, ClubPermissions::INVENTORY_VIEW,
            ClubPermissions::TEAMS_EDIT,
        ]],
        'treasurer' => ['name' => 'Kassenwart', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_MANAGE,
            ClubPermissions::FILES_MANAGE,
        ]],
        'auditor' => ['name' => 'Kassenprüfer', 'permissions' => [ClubPermissions::FINANCE_VIEW]],
        'department_lead' => ['name' => 'Abteilungsleiter', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::MEMBERS_MANAGE, ClubPermissions::FINANCE_VIEW,
            ClubPermissions::EVENTS_MANAGE, ClubPermissions::FILES_MANAGE, ClubPermissions::INVENTORY_VIEW,
            ClubPermissions::INVENTORY_MANAGE,
            ClubPermissions::TEAMS_EDIT,
        ]],
        'trainer_caretaker' => ['name' => 'Trainer und Betreuer', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::EVENTS_MANAGE, ClubPermissions::FILES_MANAGE,
            ClubPermissions::INVENTORY_VIEW, ClubPermissions::INVENTORY_MANAGE,
        ]],
        'member' => ['name' => 'Mitglied', 'permissions' => [ClubPermissions::MEMBERS_VIEW, ClubPermissions::INVENTORY_VIEW]],
        'guardian' => ['name' => 'Eltern und Sorgeberechtigte', 'permissions' => [ClubPermissions::MEMBERS_VIEW]],
        'helper_staff' => ['name' => 'Helfer und Mitarbeiter', 'permissions' => [ClubPermissions::EVENTS_MANAGE, ClubPermissions::INVENTORY_VIEW]],
        'external_contact' => ['name' => 'Externe Ansprechpartner', 'permissions' => []],
        'membership_office' => ['name' => 'Mitgliederverwaltung', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::MEMBERS_MANAGE,
            ClubPermissions::MEMBERS_EDIT, ClubPermissions::MEMBERS_APPROVE,
        ]],
        'membership_readonly' => ['name' => 'Mitgliederliste (Lesen)', 'permissions' => [ClubPermissions::MEMBERS_VIEW]],
        'finance_assistant' => ['name' => 'Buchhaltungsassistenz', 'permissions' => [
            ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_EDIT,
        ]],
        'finance_approver' => ['name' => 'Finanzfreigabe', 'permissions' => [
            ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_APPROVE,
        ]],
        'event_organizer' => ['name' => 'Veranstaltungsorganisation', 'permissions' => [
            ClubPermissions::EVENTS_MANAGE, ClubPermissions::EVENTS_EDIT,
            ClubPermissions::ANNOUNCEMENTS_EDIT,
        ]],
        'communications_editor' => ['name' => 'Öffentlichkeitsarbeit', 'permissions' => [
            ClubPermissions::CONTENT_MANAGE, ClubPermissions::ANNOUNCEMENTS_EDIT,
            ClubPermissions::ANNOUNCEMENTS_PUBLISH, ClubPermissions::CLUB_BRANDING_EDIT,
        ]],
        'team_coordinator' => ['name' => 'Teamkoordination', 'permissions' => [
            ClubPermissions::MEMBERS_VIEW, ClubPermissions::TEAMS_EDIT,
            ClubPermissions::EVENTS_MANAGE, ClubPermissions::TRAINER_COCKPIT_VIEW,
        ]],
        'inventory_steward' => ['name' => 'Materialverwaltung', 'permissions' => [
            ClubPermissions::INVENTORY_VIEW, ClubPermissions::INVENTORY_MANAGE,
            ClubPermissions::INVENTORY_EDIT, ClubPermissions::INVENTORY_APPROVE,
        ]],
        'sponsorship_coordinator' => ['name' => 'Sponsoring', 'permissions' => [
            ClubPermissions::SPONSORS_EDIT, ClubPermissions::CONTENT_MANAGE,
        ]],
        'recruiting_coordinator' => ['name' => 'Personal und Ehrenamt', 'permissions' => [
            ClubPermissions::RECRUITING_VIEW, ClubPermissions::RECRUITING_EDIT,
            ClubPermissions::RECRUITING_CONTACT, ClubPermissions::JOBS_EDIT,
            ClubPermissions::JOBS_PUBLISH,
        ]],
        'support_coordinator' => ['name' => 'Mitgliederanfragen', 'permissions' => [
            ClubPermissions::SUPPORT_VIEW, ClubPermissions::SUPPORT_EDIT,
            ClubPermissions::SUPPORT_ASSIGN, ClubPermissions::SUPPORT_RESOLVE,
        ]],
        'documents_steward' => ['name' => 'Dokumentenverwaltung', 'permissions' => [
            ClubPermissions::FILES_VIEW, ClubPermissions::FILES_EDIT,
            ClubPermissions::FILES_SHARE, ClubPermissions::POLICY_DOCUMENTS_VIEW,
            ClubPermissions::POLICY_DOCUMENTS_EDIT,
        ]],
        'governance_secretary' => ['name' => 'Gremienprotokolle', 'permissions' => [
            ClubPermissions::GOVERNANCE_VIEW, ClubPermissions::GOVERNANCE_EDIT,
            ClubPermissions::ORGANIZATION_VIEW,
        ]],
    ];

    private const GUIDANCE = [
        'club_administrator' => ['description' => 'Vollzugriff einschließlich Rollen, Finanzen und Löschen. Nur einer ausdrücklich bevollmächtigten Stellvertretung geben.', 'scenario' => 'Wenn der einzige Vorstand ausfällt, kann diese Person den Verein vollständig weiterverwalten.', 'risk' => 'high'],
        'board' => ['description' => 'Überblick über Mitglieder, Finanzen und Vereinsbetrieb; ohne automatische Rollenvergabe oder Finanzfreigabe.', 'scenario' => 'Ein weiteres Vorstandsmitglied liest Berichte, koordiniert Termine und veröffentlicht Mitteilungen.', 'risk' => 'elevated'],
        'office' => ['description' => 'Laufende Geschäftsstelle mit Mitglieder-, Team-, Datei- und Terminverwaltung; ohne Finanzverwaltung.', 'scenario' => 'Eine ehrenamtliche Person bearbeitet Mitgliedsanfragen und organisiert den Vereinsalltag.', 'risk' => 'elevated'],
        'treasurer' => ['description' => 'Finanzverwaltung mit Einsicht in Mitglieder und Dateien; keine Rollenvergabe.', 'scenario' => 'Der Kassenwart prüft Beiträge, bearbeitet Buchungen und bereitet den Jahresabschluss vor.', 'risk' => 'elevated'],
        'auditor' => ['description' => 'Nur Einsicht in Finanzen, keine Änderungen oder Freigaben.', 'scenario' => 'Die Kassenprüfung kontrolliert Buchungen vor der Mitgliederversammlung.', 'risk' => 'normal'],
        'department_lead' => ['description' => 'Verwaltung einer Abteilung mit Mitgliedern, Teams, Terminen und Material.', 'scenario' => 'Die Abteilungsleitung plant Training und Ausleihen für ihre Abteilung.', 'risk' => 'elevated'],
        'trainer_caretaker' => ['description' => 'Betreuung von Sportlern, Terminen, Dateien und Material; keine Finanzen.', 'scenario' => 'Ein Trainer organisiert Übungstermine und leiht Material für sein Team aus.', 'risk' => 'normal'],
        'member' => ['description' => 'Grundlegende Einsicht in Mitglieder und Material.', 'scenario' => 'Ein Mitglied findet Ansprechpersonen und sieht ausleihbares Vereinsmaterial.', 'risk' => 'normal'],
        'guardian' => ['description' => 'Eingeschränkte Mitgliedereinsicht für Sorgeberechtigte.', 'scenario' => 'Ein Elternteil sieht Vereinskontakte, ohne Verwaltungsaufgaben zu übernehmen.', 'risk' => 'normal'],
        'helper_staff' => ['description' => 'Unterstützung bei Veranstaltungen und Material.', 'scenario' => 'Ein Helfer organisiert einen Spieltag und prüft verfügbares Material.', 'risk' => 'normal'],
        'external_contact' => ['description' => 'Kontaktrolle ohne zusätzliche Verwaltungsrechte.', 'scenario' => 'Eine externe Fachperson ist als Ansprechpartner dokumentiert, erhält aber keinen Verwaltungszugriff.', 'risk' => 'normal'],
        'membership_office' => ['description' => 'Mitglieder ansehen, Daten bearbeiten und Mitgliedsvorgänge freigeben; keine Rollenvergabe oder Löschung.', 'scenario' => 'Eine Person aus der Geschäftsstelle bearbeitet Aufnahmeanträge und korrigiert Kontaktdaten.', 'risk' => 'elevated'],
        'membership_readonly' => ['description' => 'Mitgliederinformationen lesen, ohne sie zu ändern.', 'scenario' => 'Eine Teamleitung prüft die Mitgliederliste für die Planung.', 'risk' => 'normal'],
        'finance_assistant' => ['description' => 'Finanzdaten ansehen und bearbeiten, aber nicht freigeben oder löschen.', 'scenario' => 'Eine Hilfskraft erfasst Belege; der Kassenwart gibt die Vorgänge später frei.', 'risk' => 'elevated'],
        'finance_approver' => ['description' => 'Finanzvorgänge ansehen und freigeben, ohne Stammdaten zu bearbeiten.', 'scenario' => 'Ein Vorstandsmitglied prüft vorbereitete Ausgaben und erteilt die Freigabe.', 'risk' => 'elevated'],
        'event_organizer' => ['description' => 'Veranstaltungen planen und Mitteilungen entwerfen; keine Veröffentlichung von Mitteilungen.', 'scenario' => 'Eine Person plant das Sommerfest und bereitet die Ankündigung für den Vorstand vor.', 'risk' => 'normal'],
        'communications_editor' => ['description' => 'Inhalte, Mitteilungen und Vereinsauftritt bearbeiten und Mitteilungen veröffentlichen.', 'scenario' => 'Die Pressewartin veröffentlicht eine Terminänderung und pflegt das Vereinslogo.', 'risk' => 'elevated'],
        'team_coordinator' => ['description' => 'Teams und Termine organisieren und den Trainerbereich sehen; keine Mitglieder- oder Finanzverwaltung.', 'scenario' => 'Eine Koordinatorin teilt Spieler einem Team zu und plant Trainingstermine.', 'risk' => 'normal'],
        'inventory_steward' => ['description' => 'Inventar, Wartungen, Ausleihen und Rückgaben verwalten; keine Gegenstände löschen.', 'scenario' => 'Der Materialwart gibt Trikots aus und bucht Rückgaben.', 'risk' => 'normal'],
        'sponsorship_coordinator' => ['description' => 'Sponsoren und dazugehörige Inhalte bearbeiten; keine Verträge oder Finanzfreigaben über diese Rolle.', 'scenario' => 'Eine Person pflegt Sponsorenprofile und bereitet eine Partnerdarstellung vor.', 'risk' => 'normal'],
        'recruiting_coordinator' => ['description' => 'Stellen veröffentlichen und mit Bewerbenden Kontakt halten; keine Mitgliederrollen vergeben.', 'scenario' => 'Eine Ehrenamtskoordination veröffentlicht eine Trainerstelle und beantwortet Bewerbungen.', 'risk' => 'elevated'],
        'support_coordinator' => ['description' => 'Mitgliederanfragen ansehen, bearbeiten, zuweisen und abschließen.', 'scenario' => 'Eine Person übernimmt offene Anfragen und leitet sie an die zuständige Stelle weiter.', 'risk' => 'normal'],
        'documents_steward' => ['description' => 'Dateien und Richtliniendokumente pflegen und teilen; keine Löschung.', 'scenario' => 'Die Schriftführung lädt die neue Beitragsordnung hoch und teilt sie mit Verantwortlichen.', 'risk' => 'elevated'],
        'governance_secretary' => ['description' => 'Gremien und Funktionen dokumentieren; keine Löschung oder Rollenvergabe.', 'scenario' => 'Die Schriftführung erfasst Sitzungen und aktualisiert Vorstandsprotokolle.', 'risk' => 'normal'],
    ];

    public static function payload(): array
    {
        return collect(self::DEFINITIONS)->map(fn (array $definition, string $key) => [
            'key' => $key,
            'name' => $definition['name'],
            'permissions' => $definition['permissions'],
            ...self::GUIDANCE[$key],
        ])->values()->all();
    }
}
