<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;

/**
 * Club-scoped permissions. These are deliberately independent from global
 * Spatie permissions so that one club cannot grant access to another club.
 */
class ClubPermissions
{
    public const MEMBERS_VIEW = 'members.view';

    public const MEMBERS_MANAGE = 'members.manage';

    public const MEMBERS_ROLES = 'members.roles';

    public const FINANCE_VIEW = 'finance.view';

    public const FINANCE_MANAGE = 'finance.manage';

    public const CONTENT_MANAGE = 'content.manage';

    public const EVENTS_MANAGE = 'events.manage';

    public const FILES_MANAGE = 'files.manage';

    public const SURVEYS_MANAGE = 'surveys.manage';

    public const ANNOUNCEMENTS_MANAGE = 'announcements.manage';

    public const SUPPORT_MANAGE = 'support.manage';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_MANAGE = 'inventory.manage';

    public const ALL = [
        self::MEMBERS_VIEW,
        self::MEMBERS_MANAGE,
        self::MEMBERS_ROLES,
        self::FINANCE_VIEW,
        self::FINANCE_MANAGE,
        self::CONTENT_MANAGE,
        self::EVENTS_MANAGE,
        self::FILES_MANAGE,
        self::SURVEYS_MANAGE,
        self::ANNOUNCEMENTS_MANAGE,
        self::SUPPORT_MANAGE,
        self::INVENTORY_VIEW,
        self::INVENTORY_MANAGE,
    ];

    public const LABELS = [
        self::MEMBERS_VIEW => 'Mitglieder ansehen',
        self::MEMBERS_MANAGE => 'Mitglieder verwalten',
        self::MEMBERS_ROLES => 'Rollen und Berechtigungen verwalten',
        self::FINANCE_VIEW => 'Finanzen ansehen',
        self::FINANCE_MANAGE => 'Finanzen verwalten',
        self::CONTENT_MANAGE => 'Vereinsinhalte verwalten',
        self::EVENTS_MANAGE => 'Termine und Veranstaltungen verwalten',
        self::FILES_MANAGE => 'Dateien verwalten',
        self::SURVEYS_MANAGE => 'Umfragen verwalten',
        self::ANNOUNCEMENTS_MANAGE => 'Ankündigungen verwalten',
        self::SUPPORT_MANAGE => 'Support verwalten',
        self::INVENTORY_VIEW => 'Vereinsinventar ansehen und ausleihen',
        self::INVENTORY_MANAGE => 'Vereinsinventar und Ausleihen verwalten',
    ];

    private const ROLE_DEFAULTS = [
        'owner' => self::ALL,
        'admin' => self::ALL,
        'manager' => [
            self::MEMBERS_VIEW,
            self::MEMBERS_MANAGE,
            self::FINANCE_VIEW,
            self::CONTENT_MANAGE,
            self::EVENTS_MANAGE,
            self::FILES_MANAGE,
            self::SURVEYS_MANAGE,
            self::ANNOUNCEMENTS_MANAGE,
            self::SUPPORT_MANAGE,
            self::INVENTORY_VIEW,
            self::INVENTORY_MANAGE,
        ],
        'academy_manager' => [
            self::MEMBERS_VIEW,
            self::MEMBERS_MANAGE,
            self::CONTENT_MANAGE,
            self::EVENTS_MANAGE,
            self::FILES_MANAGE,
            self::INVENTORY_VIEW,
            self::INVENTORY_MANAGE,
        ],
        'financial_controller' => [
            self::MEMBERS_VIEW,
            self::FINANCE_VIEW,
            self::FINANCE_MANAGE,
        ],
        'trainer' => [
            self::MEMBERS_VIEW,
            self::EVENTS_MANAGE,
            self::FILES_MANAGE,
            self::INVENTORY_VIEW,
            self::INVENTORY_MANAGE,
        ],
        'member' => [self::MEMBERS_VIEW, self::INVENTORY_VIEW],
    ];

    public static function catalog(): array
    {
        return collect(self::ALL)->map(fn (string $key) => [
            'key' => $key,
            'label' => self::LABELS[$key] ?? $key,
        ])->values()->all();
    }

    public static function defaultsForRoles(array $roles): array
    {
        return collect($roles)
            ->flatMap(fn (string $role) => self::ROLE_DEFAULTS[$role] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeOverrides(mixed $overrides): array
    {
        if (! is_array($overrides)) {
            return [];
        }

        return collect($overrides)
            ->filter(fn ($value, $key) => in_array((string) $key, self::ALL, true) && is_bool($value))
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (bool) $value])
            ->all();
    }

    public static function effectiveFor(Club $club, User $user): array
    {
        if ($club->owner_id === $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return array_fill_keys(self::ALL, true);
        }

        $member = $club->users()->where('users.id', $user->id)->first();
        if (! $member) {
            return array_fill_keys(self::ALL, false);
        }

        $roles = ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? []);
        $defaults = array_fill_keys(self::defaultsForRoles($roles), true);
        $overrides = self::normalizeOverrides($member->pivot?->permission_overrides);

        return collect(self::ALL)->mapWithKeys(fn (string $key) => [
            $key => array_key_exists($key, $overrides) ? $overrides[$key] : ($defaults[$key] ?? false),
        ])->all();
    }

    public static function allows(Club $club, User $user, string $permission): bool
    {
        return in_array($permission, self::ALL, true)
            && (bool) (self::effectiveFor($club, $user)[$permission] ?? false);
    }

    public static function editableBy(Club $club, User $user): bool
    {
        return $club->owner_id === $user->id
            || $user->hasAnyRole(Roles::FULL_ACCESS)
            || self::allows($club, $user, self::MEMBERS_ROLES);
    }
}
