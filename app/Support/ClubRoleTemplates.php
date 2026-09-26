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
    ];

    public static function payload(): array
    {
        return collect(self::DEFINITIONS)->map(fn (array $definition, string $key) => [
            'key' => $key,
            'name' => $definition['name'],
            'permissions' => $definition['permissions'],
        ])->values()->all();
    }
}
