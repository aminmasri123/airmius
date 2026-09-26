<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubInventoryItem;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Club-scoped permissions. These are deliberately independent from global
 * Spatie permissions so that one club cannot grant access to another club.
 */
class ClubPermissions
{
    public const MEMBERS_VIEW = 'members.view';

    public const MEMBERS_MANAGE = 'members.manage';

    public const MEMBERS_EDIT = 'members.edit';

    public const MEMBERS_EXPORT = 'members.export';

    public const MEMBERS_APPROVE = 'members.approve';

    public const MEMBERS_DELETE = 'members.delete';

    public const MEMBERS_ROLES = 'members.roles';

    public const FINANCE_VIEW = 'finance.view';

    public const FINANCE_MANAGE = 'finance.manage';

    public const FINANCE_EDIT = 'finance.edit';

    public const FINANCE_EXPORT = 'finance.export';

    public const FINANCE_APPROVE = 'finance.approve';

    public const FINANCE_DELETE = 'finance.delete';

    public const SUBSCRIPTIONS_VIEW = 'subscriptions.view';

    public const SUBSCRIPTIONS_EDIT = 'subscriptions.edit';

    public const CONTENT_MANAGE = 'content.manage';

    public const CLUB_PROFILE_EDIT = 'club_profile.edit';

    public const CLUB_LEGAL_EDIT = 'club_legal.edit';

    public const CLUB_CONTACT_EDIT = 'club_contact.edit';

    public const CLUB_BRANDING_EDIT = 'club_branding.edit';

    public const SPONSORS_EDIT = 'sponsors.edit';

    public const SPONSORS_DELETE = 'sponsors.delete';

    public const JOBS_EDIT = 'jobs.edit';

    public const JOBS_PUBLISH = 'jobs.publish';

    public const JOBS_DELETE = 'jobs.delete';

    public const RECRUITING_VIEW = 'recruiting.view';

    public const RECRUITING_EDIT = 'recruiting.edit';

    public const RECRUITING_CONTACT = 'recruiting.contact';

    public const RECRUITING_DELETE = 'recruiting.delete';

    public const TRAINING_EXERCISES_VIEW = 'training_exercises.view';

    public const TRAINING_EXERCISES_EDIT = 'training_exercises.edit';

    public const TRAINING_EXERCISES_DELETE = 'training_exercises.delete';

    public const TRAINING_SESSIONS_VIEW = 'training_sessions.view';

    public const TRAINING_SESSIONS_EDIT = 'training_sessions.edit';

    public const TRAINING_SESSIONS_DELETE = 'training_sessions.delete';

    public const COMMERCE_ADDONS_PURCHASE = 'commerce_addons.purchase';

    public const COMMERCE_PRODUCTS_EDIT = 'commerce_products.edit';

    public const ADVERTISING_EDIT = 'advertising.edit';

    public const WEBSITE_REQUEST_CREATE = 'website_request.create';

    public const COCKPIT_VIEW = 'cockpit.view';

    public const TRAINER_COCKPIT_VIEW = 'trainer_cockpit.view';

    public const TRAINING_PLANS_EDIT = 'training_plans.edit';

    public const TEAM_CASHBOX_MANAGE = 'team_cashbox.manage';

    public const YEAR_PERIODS_VIEW = 'year_periods.view';

    public const YEAR_PERIODS_EDIT = 'year_periods.edit';

    public const YEAR_PERIODS_DELETE = 'year_periods.delete';

    public const ORGANIZATION_VIEW = 'organization.view';

    public const ORGANIZATION_EDIT = 'organization.edit';

    public const ORGANIZATION_DELETE = 'organization.delete';

    public const POLICY_DOCUMENTS_VIEW = 'policy_documents.view';

    public const POLICY_DOCUMENTS_EDIT = 'policy_documents.edit';

    public const POLICY_DOCUMENTS_DOWNLOAD = 'policy_documents.download';

    public const POLICY_DOCUMENTS_DELETE = 'policy_documents.delete';

    public const GOVERNANCE_VIEW = 'governance.view';

    public const GOVERNANCE_EDIT = 'governance.edit';

    public const GOVERNANCE_DELETE = 'governance.delete';

    public const METADATA_VIEW = 'metadata.view';

    public const METADATA_EDIT = 'metadata.edit';

    public const METADATA_DELETE = 'metadata.delete';

    public const EVENTS_MANAGE = 'events.manage';

    public const EVENTS_EDIT = 'events.edit';

    public const EVENTS_DELETE = 'events.delete';

    public const TEAMS_EDIT = 'teams.edit';

    public const TEAMS_DELETE = 'teams.delete';

    public const FILES_MANAGE = 'files.manage';

    public const FILES_VIEW = 'files.view';

    public const FILES_EDIT = 'files.edit';

    public const FILES_DELETE = 'files.delete';

    public const FILES_EXPORT = 'files.export';

    public const FILES_SHARE = 'files.share';

    public const SURVEYS_MANAGE = 'surveys.manage';

    public const SURVEYS_EDIT = 'surveys.edit';

    public const SURVEYS_CLOSE = 'surveys.close';

    public const SURVEYS_DELETE = 'surveys.delete';

    public const ANNOUNCEMENTS_MANAGE = 'announcements.manage';

    public const ANNOUNCEMENTS_EDIT = 'announcements.edit';

    public const ANNOUNCEMENTS_PUBLISH = 'announcements.publish';

    public const ANNOUNCEMENTS_DELETE = 'announcements.delete';

    public const SUPPORT_MANAGE = 'support.manage';

    public const SUPPORT_VIEW = 'support.view';

    public const SUPPORT_EDIT = 'support.edit';

    public const SUPPORT_ASSIGN = 'support.assign';

    public const SUPPORT_RESOLVE = 'support.resolve';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_MANAGE = 'inventory.manage';

    public const INVENTORY_EDIT = 'inventory.edit';

    public const INVENTORY_APPROVE = 'inventory.approve';

    public const INVENTORY_DELETE = 'inventory.delete';

    public const ALL = [
        self::MEMBERS_VIEW,
        self::MEMBERS_MANAGE,
        self::MEMBERS_EDIT,
        self::MEMBERS_EXPORT,
        self::MEMBERS_APPROVE,
        self::MEMBERS_DELETE,
        self::MEMBERS_ROLES,
        self::FINANCE_VIEW,
        self::FINANCE_MANAGE,
        self::FINANCE_EDIT,
        self::FINANCE_EXPORT,
        self::FINANCE_APPROVE,
        self::FINANCE_DELETE,
        self::SUBSCRIPTIONS_VIEW,
        self::SUBSCRIPTIONS_EDIT,
        self::CONTENT_MANAGE,
        self::CLUB_PROFILE_EDIT,
        self::CLUB_LEGAL_EDIT,
        self::CLUB_CONTACT_EDIT,
        self::CLUB_BRANDING_EDIT,
        self::SPONSORS_EDIT,
        self::SPONSORS_DELETE,
        self::JOBS_EDIT,
        self::JOBS_PUBLISH,
        self::JOBS_DELETE,
        self::RECRUITING_VIEW,
        self::RECRUITING_EDIT,
        self::RECRUITING_CONTACT,
        self::RECRUITING_DELETE,
        self::TRAINING_EXERCISES_VIEW,
        self::TRAINING_EXERCISES_EDIT,
        self::TRAINING_EXERCISES_DELETE,
        self::TRAINING_SESSIONS_VIEW,
        self::TRAINING_SESSIONS_EDIT,
        self::TRAINING_SESSIONS_DELETE,
        self::COMMERCE_ADDONS_PURCHASE,
        self::COMMERCE_PRODUCTS_EDIT,
        self::ADVERTISING_EDIT,
        self::WEBSITE_REQUEST_CREATE,
        self::COCKPIT_VIEW,
        self::TRAINER_COCKPIT_VIEW,
        self::TRAINING_PLANS_EDIT,
        self::TEAM_CASHBOX_MANAGE,
        self::YEAR_PERIODS_VIEW,
        self::YEAR_PERIODS_EDIT,
        self::YEAR_PERIODS_DELETE,
        self::ORGANIZATION_VIEW,
        self::ORGANIZATION_EDIT,
        self::ORGANIZATION_DELETE,
        self::POLICY_DOCUMENTS_VIEW,
        self::POLICY_DOCUMENTS_EDIT,
        self::POLICY_DOCUMENTS_DOWNLOAD,
        self::POLICY_DOCUMENTS_DELETE,
        self::GOVERNANCE_VIEW,
        self::GOVERNANCE_EDIT,
        self::GOVERNANCE_DELETE,
        self::METADATA_VIEW,
        self::METADATA_EDIT,
        self::METADATA_DELETE,
        self::EVENTS_MANAGE,
        self::EVENTS_EDIT,
        self::EVENTS_DELETE,
        self::TEAMS_EDIT,
        self::TEAMS_DELETE,
        self::FILES_MANAGE,
        self::FILES_VIEW,
        self::FILES_EDIT,
        self::FILES_DELETE,
        self::FILES_EXPORT,
        self::FILES_SHARE,
        self::SURVEYS_MANAGE,
        self::SURVEYS_EDIT,
        self::SURVEYS_CLOSE,
        self::SURVEYS_DELETE,
        self::ANNOUNCEMENTS_MANAGE,
        self::ANNOUNCEMENTS_EDIT,
        self::ANNOUNCEMENTS_PUBLISH,
        self::ANNOUNCEMENTS_DELETE,
        self::SUPPORT_MANAGE,
        self::SUPPORT_VIEW,
        self::SUPPORT_EDIT,
        self::SUPPORT_ASSIGN,
        self::SUPPORT_RESOLVE,
        self::INVENTORY_VIEW,
        self::INVENTORY_MANAGE,
        self::INVENTORY_EDIT,
        self::INVENTORY_APPROVE,
        self::INVENTORY_DELETE,
    ];

    public const LABELS = [
        self::MEMBERS_VIEW => 'Mitglieder ansehen',
        self::MEMBERS_MANAGE => 'Mitglieder verwalten',
        self::MEMBERS_EDIT => 'Mitgliederdaten bearbeiten',
        self::MEMBERS_EXPORT => 'Mitgliederdaten exportieren',
        self::MEMBERS_APPROVE => 'Mitgliedervorgänge freigeben',
        self::MEMBERS_DELETE => 'Mitglieder entfernen',
        self::MEMBERS_ROLES => 'Rollen und Berechtigungen verwalten',
        self::FINANCE_VIEW => 'Finanzen ansehen',
        self::FINANCE_MANAGE => 'Finanzen verwalten',
        self::FINANCE_EDIT => 'Finanzdaten bearbeiten',
        self::FINANCE_EXPORT => 'Finanzdaten exportieren',
        self::FINANCE_APPROVE => 'Finanzvorgänge freigeben',
        self::FINANCE_DELETE => 'Finanzdaten löschen oder stornieren',
        self::SUBSCRIPTIONS_VIEW => 'Vereinsabonnements ansehen',
        self::SUBSCRIPTIONS_EDIT => 'Vereinsabonnements abschließen, kündigen und fortsetzen',
        self::CONTENT_MANAGE => 'Vereinsinhalte verwalten',
        self::CLUB_PROFILE_EDIT => 'Allgemeine Vereinsdaten bearbeiten',
        self::CLUB_LEGAL_EDIT => 'Rechtliche und steuerliche Vereinsdaten bearbeiten',
        self::CLUB_CONTACT_EDIT => 'Kontaktangaben des Vereins bearbeiten',
        self::CLUB_BRANDING_EDIT => 'Erscheinungsbild und Dokumentvorlagen bearbeiten',
        self::SPONSORS_EDIT => 'Vereinssponsoren anlegen und bearbeiten',
        self::SPONSORS_DELETE => 'Vereinssponsoren löschen',
        self::JOBS_EDIT => 'Stellen- und Ehrenamtsausschreibungen bearbeiten',
        self::JOBS_PUBLISH => 'Stellen- und Ehrenamtsausschreibungen veröffentlichen',
        self::JOBS_DELETE => 'Stellen- und Ehrenamtsausschreibungen löschen',
        self::RECRUITING_VIEW => 'Bewerbungen ansehen',
        self::RECRUITING_EDIT => 'Bewerbungsstatus und interne Notizen bearbeiten',
        self::RECRUITING_CONTACT => 'Bewerber im Vereinschat kontaktieren',
        self::RECRUITING_DELETE => 'Bewerbungen endgültig löschen',
        self::TRAINING_EXERCISES_VIEW => 'Übungsbibliothek ansehen',
        self::TRAINING_EXERCISES_EDIT => 'Vereins- und Mannschaftsübungen anlegen und bearbeiten',
        self::TRAINING_EXERCISES_DELETE => 'Vereins- und Mannschaftsübungen entfernen',
        self::TRAINING_SESSIONS_VIEW => 'Trainingseinheiten ansehen',
        self::TRAINING_SESSIONS_EDIT => 'Trainingseinheiten mit Ablauf und Material bearbeiten',
        self::TRAINING_SESSIONS_DELETE => 'Trainingseinheiten archivieren',
        self::COMMERCE_ADDONS_PURCHASE => 'Vereins-Add-ons kaufen',
        self::COMMERCE_PRODUCTS_EDIT => 'Vereinsangebote im Marketplace anlegen',
        self::ADVERTISING_EDIT => 'Werbekampagnen des Vereins anlegen',
        self::WEBSITE_REQUEST_CREATE => 'Website-Aufträge für den Verein anfragen',
        self::COCKPIT_VIEW => 'Vereins-Cockpit mit Steuerungskennzahlen ansehen',
        self::TRAINER_COCKPIT_VIEW => 'Trainer-Cockpit im zugewiesenen Bereich ansehen',
        self::TRAINING_PLANS_EDIT => 'Trainingspläne im zugewiesenen Bereich bearbeiten',
        self::TEAM_CASHBOX_MANAGE => 'Mannschaftskasse und Strafenkatalog verwalten',
        self::YEAR_PERIODS_VIEW => 'Vereinsjahre ansehen',
        self::YEAR_PERIODS_EDIT => 'Vereinsjahre anlegen und bearbeiten',
        self::YEAR_PERIODS_DELETE => 'Vereinsjahre löschen',
        self::ORGANIZATION_VIEW => 'Vereinsstruktur ansehen',
        self::ORGANIZATION_EDIT => 'Vereinsstruktur anlegen und bearbeiten',
        self::ORGANIZATION_DELETE => 'Vereinsstruktur löschen',
        self::POLICY_DOCUMENTS_VIEW => 'Vereinsdokumente ansehen',
        self::POLICY_DOCUMENTS_EDIT => 'Vereinsdokumente anlegen und bearbeiten',
        self::POLICY_DOCUMENTS_DOWNLOAD => 'Vereinsdokumente herunterladen',
        self::POLICY_DOCUMENTS_DELETE => 'Vereinsdokumente löschen',
        self::GOVERNANCE_VIEW => 'Gremien und Funktionen ansehen',
        self::GOVERNANCE_EDIT => 'Gremien und Funktionen anlegen und bearbeiten',
        self::GOVERNANCE_DELETE => 'Gremien und Funktionen löschen',
        self::METADATA_VIEW => 'Eigene Datenfelder, Kategorien und Nummernkreise ansehen',
        self::METADATA_EDIT => 'Eigene Datenfelder, Kategorien und Nummernkreise bearbeiten',
        self::METADATA_DELETE => 'Eigene Datenfelder, Kategorien und Nummernkreise löschen',
        self::EVENTS_MANAGE => 'Termine und Veranstaltungen verwalten',
        self::EVENTS_EDIT => 'Termine und Veranstaltungen bearbeiten',
        self::EVENTS_DELETE => 'Termine und Veranstaltungen löschen',
        self::TEAMS_EDIT => 'Mannschaftsdaten bearbeiten',
        self::TEAMS_DELETE => 'Mannschaften löschen',
        self::FILES_MANAGE => 'Dateien verwalten',
        self::FILES_VIEW => 'Dateien ansehen',
        self::FILES_EDIT => 'Dateien und Ordner anlegen oder bearbeiten',
        self::FILES_DELETE => 'Dateien und Ordner löschen',
        self::FILES_EXPORT => 'Dateien herunterladen oder exportieren',
        self::FILES_SHARE => 'Dateien und Ordner freigeben',
        self::SURVEYS_MANAGE => 'Umfragen verwalten',
        self::SURVEYS_EDIT => 'Umfragen anlegen und bearbeiten',
        self::SURVEYS_CLOSE => 'Umfragen schließen',
        self::SURVEYS_DELETE => 'Umfragen löschen',
        self::ANNOUNCEMENTS_MANAGE => 'Ankündigungen verwalten',
        self::ANNOUNCEMENTS_EDIT => 'Ankündigungen anlegen und bearbeiten',
        self::ANNOUNCEMENTS_PUBLISH => 'Ankündigungen veröffentlichen',
        self::ANNOUNCEMENTS_DELETE => 'Ankündigungen löschen',
        self::SUPPORT_MANAGE => 'Support verwalten',
        self::SUPPORT_VIEW => 'Supportanfragen ansehen',
        self::SUPPORT_EDIT => 'Supportanfragen bearbeiten',
        self::SUPPORT_ASSIGN => 'Supportanfragen zuweisen',
        self::SUPPORT_RESOLVE => 'Supportanfragen abschließen',
        self::INVENTORY_VIEW => 'Vereinsinventar ansehen und ausleihen',
        self::INVENTORY_MANAGE => 'Vereinsinventar und Ausleihen verwalten',
        self::INVENTORY_EDIT => 'Vereinsinventar und Wartungen bearbeiten',
        self::INVENTORY_APPROVE => 'Ausleihen freigeben und Rückgaben buchen',
        self::INVENTORY_DELETE => 'Inventargegenstände löschen',
    ];

    private const ROLE_DEFAULTS = [
        'owner' => self::ALL,
        'admin' => self::ALL,
        'manager' => [
            self::MEMBERS_VIEW,
            self::MEMBERS_MANAGE,
            self::FINANCE_VIEW,
            self::SUBSCRIPTIONS_VIEW,
            self::SUBSCRIPTIONS_EDIT,
            self::JOBS_EDIT,
            self::JOBS_PUBLISH,
            self::JOBS_DELETE,
            self::RECRUITING_VIEW,
            self::RECRUITING_EDIT,
            self::RECRUITING_CONTACT,
            self::RECRUITING_DELETE,
            self::TRAINING_EXERCISES_VIEW,
            self::TRAINING_EXERCISES_EDIT,
            self::TRAINING_EXERCISES_DELETE,
            self::TRAINING_SESSIONS_VIEW,
            self::TRAINING_SESSIONS_EDIT,
            self::TRAINING_SESSIONS_DELETE,
            self::COMMERCE_ADDONS_PURCHASE,
            self::COMMERCE_PRODUCTS_EDIT,
            self::ADVERTISING_EDIT,
            self::WEBSITE_REQUEST_CREATE,
            self::COCKPIT_VIEW,
            self::TRAINER_COCKPIT_VIEW,
            self::TEAM_CASHBOX_MANAGE,
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
            self::COMMERCE_ADDONS_PURCHASE,
            self::COMMERCE_PRODUCTS_EDIT,
            self::ADVERTISING_EDIT,
            self::WEBSITE_REQUEST_CREATE,
            self::COCKPIT_VIEW,
            self::TRAINER_COCKPIT_VIEW,
            self::TEAM_CASHBOX_MANAGE,
            self::CONTENT_MANAGE,
            self::EVENTS_MANAGE,
            self::FILES_MANAGE,
            self::INVENTORY_VIEW,
            self::INVENTORY_MANAGE,
        ],
        'financial_controller' => [
            self::MEMBERS_VIEW,
            self::FINANCE_VIEW,
            self::COMMERCE_ADDONS_PURCHASE,
            self::COMMERCE_PRODUCTS_EDIT,
            self::ADVERTISING_EDIT,
            self::WEBSITE_REQUEST_CREATE,
            self::COCKPIT_VIEW,
            self::TRAINER_COCKPIT_VIEW,
            self::TRAINING_EXERCISES_VIEW,
            self::TRAINING_SESSIONS_VIEW,
            self::TEAM_CASHBOX_MANAGE,
            self::FINANCE_MANAGE,
            self::YEAR_PERIODS_VIEW,
            self::ORGANIZATION_VIEW,
            self::POLICY_DOCUMENTS_VIEW,
            self::POLICY_DOCUMENTS_DOWNLOAD,
            self::GOVERNANCE_VIEW,
        ],
        'trainer' => [
            self::MEMBERS_VIEW,
            self::TRAINER_COCKPIT_VIEW,
            self::TRAINING_PLANS_EDIT,
            self::TRAINING_EXERCISES_VIEW,
            self::TRAINING_SESSIONS_VIEW,
            self::TRAINING_SESSIONS_EDIT,
            self::YEAR_PERIODS_VIEW,
            self::ORGANIZATION_VIEW,
            self::POLICY_DOCUMENTS_VIEW,
            self::POLICY_DOCUMENTS_DOWNLOAD,
            self::GOVERNANCE_VIEW,
            self::EVENTS_MANAGE,
            self::FILES_MANAGE,
            self::INVENTORY_VIEW,
            self::INVENTORY_MANAGE,
        ],
        'member' => [
            self::MEMBERS_VIEW, self::TRAINING_EXERCISES_VIEW, self::YEAR_PERIODS_VIEW, self::ORGANIZATION_VIEW,
            self::POLICY_DOCUMENTS_VIEW, self::POLICY_DOCUMENTS_DOWNLOAD, self::INVENTORY_VIEW,
            self::GOVERNANCE_VIEW,
        ],
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

    public static function effectiveFor(Club $club, User $user, ?array $scope = null): array
    {
        if ($club->owner_id === $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return array_fill_keys(self::ALL, true);
        }

        $member = $club->users()->where('users.id', $user->id)->first();
        if (! $member) {
            return array_fill_keys(self::ALL, false);
        }

        $membershipEligible = in_array($member->pivot?->membership_status, [null, 'active', 'non_member'], true);
        if (! $membershipEligible) {
            return array_fill_keys(self::ALL, false);
        }

        $roles = ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? []);
        $defaults = array_fill_keys(self::defaultsForRoles($roles), true);
        $overrides = self::normalizeOverrides($member->pivot?->permission_overrides);
        $delegated = self::delegatedFor($club, $user, $scope);
        $configuredRoles = self::configuredRolePermissions($club, $user, $scope);

        $effective = collect(self::ALL)->mapWithKeys(fn (string $key) => [
            $key => array_key_exists($key, $overrides)
                ? $overrides[$key]
                : ((bool) ($delegated[$key] ?? false)
                    || (bool) ($configuredRoles[$key] ?? false)
                    || (bool) ($defaults[$key] ?? false)),
        ])->all();

        foreach ([
            self::MEMBERS_MANAGE => [self::MEMBERS_EDIT, self::MEMBERS_EXPORT, self::MEMBERS_APPROVE, self::MEMBERS_DELETE],
            self::FINANCE_MANAGE => [
                self::FINANCE_EDIT, self::FINANCE_EXPORT, self::FINANCE_APPROVE, self::FINANCE_DELETE,
                self::SUBSCRIPTIONS_VIEW, self::SUBSCRIPTIONS_EDIT,
            ],
            self::CONTENT_MANAGE => [
                self::CLUB_PROFILE_EDIT, self::CLUB_LEGAL_EDIT,
                self::CLUB_CONTACT_EDIT, self::CLUB_BRANDING_EDIT,
                self::SPONSORS_EDIT, self::SPONSORS_DELETE,
                self::YEAR_PERIODS_VIEW, self::YEAR_PERIODS_EDIT, self::YEAR_PERIODS_DELETE,
                self::ORGANIZATION_VIEW, self::ORGANIZATION_EDIT, self::ORGANIZATION_DELETE,
                self::POLICY_DOCUMENTS_VIEW, self::POLICY_DOCUMENTS_EDIT,
                self::POLICY_DOCUMENTS_DOWNLOAD, self::POLICY_DOCUMENTS_DELETE,
                self::GOVERNANCE_VIEW, self::GOVERNANCE_EDIT, self::GOVERNANCE_DELETE,
                self::METADATA_VIEW, self::METADATA_EDIT, self::METADATA_DELETE,
                self::TEAMS_EDIT, self::TEAMS_DELETE,
            ],
            self::EVENTS_MANAGE => [
                self::EVENTS_EDIT, self::EVENTS_DELETE,
                self::TRAINING_EXERCISES_VIEW, self::TRAINING_EXERCISES_EDIT, self::TRAINING_EXERCISES_DELETE,
            ],
            self::FILES_MANAGE => [
                self::FILES_VIEW, self::FILES_EDIT, self::FILES_DELETE, self::FILES_EXPORT, self::FILES_SHARE,
            ],
            self::INVENTORY_MANAGE => [self::INVENTORY_VIEW, self::INVENTORY_EDIT, self::INVENTORY_APPROVE, self::INVENTORY_DELETE],
            self::ANNOUNCEMENTS_MANAGE => [self::ANNOUNCEMENTS_EDIT, self::ANNOUNCEMENTS_PUBLISH, self::ANNOUNCEMENTS_DELETE],
            self::SURVEYS_MANAGE => [self::SURVEYS_EDIT, self::SURVEYS_CLOSE, self::SURVEYS_DELETE],
            self::SUPPORT_MANAGE => [self::SUPPORT_VIEW, self::SUPPORT_EDIT, self::SUPPORT_ASSIGN, self::SUPPORT_RESOLVE],
        ] as $legacyPermission => $actionPermissions) {
            if (! ($effective[$legacyPermission] ?? false)) {
                continue;
            }
            foreach ($actionPermissions as $actionPermission) {
                if (! array_key_exists($actionPermission, $overrides)) {
                    $effective[$actionPermission] = true;
                }
            }
        }

        if (! array_key_exists(self::YEAR_PERIODS_VIEW, $overrides)
            && (($effective[self::YEAR_PERIODS_EDIT] ?? false) || ($effective[self::YEAR_PERIODS_DELETE] ?? false))) {
            $effective[self::YEAR_PERIODS_VIEW] = true;
        }

        if (! array_key_exists(self::SUBSCRIPTIONS_VIEW, $overrides)
            && (($effective[self::FINANCE_VIEW] ?? false) || ($effective[self::SUBSCRIPTIONS_EDIT] ?? false))) {
            $effective[self::SUBSCRIPTIONS_VIEW] = true;
        }

        if (! array_key_exists(self::RECRUITING_VIEW, $overrides)
            && (($effective[self::RECRUITING_EDIT] ?? false)
                || ($effective[self::RECRUITING_CONTACT] ?? false)
                || ($effective[self::RECRUITING_DELETE] ?? false))) {
            $effective[self::RECRUITING_VIEW] = true;
        }

        if (! array_key_exists(self::TRAINING_EXERCISES_VIEW, $overrides)
            && (($effective[self::TRAINING_EXERCISES_EDIT] ?? false)
                || ($effective[self::TRAINING_EXERCISES_DELETE] ?? false))) {
            $effective[self::TRAINING_EXERCISES_VIEW] = true;
        }

        if (! array_key_exists(self::TRAINING_SESSIONS_VIEW, $overrides)
            && (($effective[self::TRAINING_SESSIONS_EDIT] ?? false)
                || ($effective[self::TRAINING_SESSIONS_DELETE] ?? false))) {
            $effective[self::TRAINING_SESSIONS_VIEW] = true;
        }

        if (! array_key_exists(self::ORGANIZATION_VIEW, $overrides)
            && (($effective[self::ORGANIZATION_EDIT] ?? false) || ($effective[self::ORGANIZATION_DELETE] ?? false))) {
            $effective[self::ORGANIZATION_VIEW] = true;
        }

        if (! array_key_exists(self::POLICY_DOCUMENTS_VIEW, $overrides)
            && (($effective[self::POLICY_DOCUMENTS_EDIT] ?? false)
                || ($effective[self::POLICY_DOCUMENTS_DOWNLOAD] ?? false)
                || ($effective[self::POLICY_DOCUMENTS_DELETE] ?? false))) {
            $effective[self::POLICY_DOCUMENTS_VIEW] = true;
        }

        if (! array_key_exists(self::GOVERNANCE_VIEW, $overrides)
            && (($effective[self::GOVERNANCE_EDIT] ?? false) || ($effective[self::GOVERNANCE_DELETE] ?? false))) {
            $effective[self::GOVERNANCE_VIEW] = true;
        }

        if (! array_key_exists(self::METADATA_VIEW, $overrides)
            && (($effective[self::METADATA_EDIT] ?? false) || ($effective[self::METADATA_DELETE] ?? false))) {
            $effective[self::METADATA_VIEW] = true;
        }

        return $effective;
    }

    public static function allows(Club $club, User $user, string $permission): bool
    {
        return in_array($permission, self::ALL, true)
            && (bool) (self::effectiveFor($club, $user)[$permission] ?? false);
    }

    public static function allowsAnyClub(User $user, array $permissions): bool
    {
        $permissions = collect($permissions)
            ->filter(fn ($permission) => is_string($permission) && in_array($permission, self::ALL, true))
            ->unique()
            ->values();

        if ($permissions->isEmpty()) {
            return false;
        }

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        return Club::query()
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
            })
            ->get()
            ->contains(fn (Club $club) => $permissions->contains(
                fn (string $permission) => self::allows($club, $user, $permission)
            ));
    }

    public static function explicitlyDenies(Club $club, User $user, string $permission): bool
    {
        if (! in_array($permission, self::ALL, true)
            || $club->owner_id === $user->id
            || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return false;
        }

        $member = $club->users()->where('users.id', $user->id)->first();
        $overrides = self::normalizeOverrides($member?->pivot?->permission_overrides);

        return array_key_exists($permission, $overrides) && $overrides[$permission] === false;
    }

    public static function allowsInScope(Club $club, User $user, string $permission, string $scopeType, int $scopeId): bool
    {
        if (! in_array($scopeType, ['department', 'team'], true) || $scopeId < 1) {
            return false;
        }

        return in_array($permission, self::ALL, true)
            && (bool) (self::effectiveFor($club, $user, ['type' => $scopeType, 'id' => $scopeId])[$permission] ?? false);
    }

    public static function allowsForTeam(Team $team, User $user, string $permission): bool
    {
        $club = $team->club;
        if (! $club) {
            return false;
        }

        if (self::allowsInScope($club, $user, $permission, 'team', (int) $team->id)) {
            return true;
        }

        return $team->club_department_id
            && self::allowsInScope($club, $user, $permission, 'department', (int) $team->club_department_id);
    }

    public static function allowsTeamDepartmentChange(Team $team, User $user, ?int $newDepartmentId): bool
    {
        $club = $team->club;
        if (! $club) {
            return false;
        }

        if (self::allows($club, $user, self::TEAMS_EDIT)
            || self::allowsInScope($club, $user, self::TEAMS_EDIT, 'team', (int) $team->id)) {
            return true;
        }

        return $newDepartmentId
            && self::allowsInScope($club, $user, self::TEAMS_EDIT, 'department', $newDepartmentId);
    }

    public static function allowsForFileScope(
        User $user,
        string $permission,
        ?int $clubId = null,
        ?int $teamId = null,
        ?int $eventId = null,
    ): bool {
        if ($eventId) {
            $event = Event::query()->with(['club', 'team.club'])->find($eventId);
            if (! $event) {
                return false;
            }

            if ($event->team) {
                return self::allowsForTeam($event->team, $user, $permission);
            }

            $club = $event->resolvedClub();

            return $club ? self::allows($club, $user, $permission) : false;
        }

        if ($teamId) {
            $team = Team::query()->with('club')->find($teamId);

            return $team ? self::allowsForTeam($team, $user, $permission) : false;
        }

        if ($clubId) {
            $club = Club::query()->find($clubId);

            return $club ? self::allows($club, $user, $permission) : false;
        }

        return false;
    }

    public static function explicitlyDeniesForFileScope(
        User $user,
        string $permission,
        ?int $clubId = null,
        ?int $teamId = null,
        ?int $eventId = null,
    ): bool {
        $club = null;

        if ($eventId) {
            $event = Event::query()->with(['club', 'team.club'])->find($eventId);
            $club = $event?->resolvedClub();
        } elseif ($teamId) {
            $club = Team::query()->with('club')->find($teamId)?->club;
        } elseif ($clubId) {
            $club = Club::query()->find($clubId);
        }

        return $club ? self::explicitlyDenies($club, $user, $permission) : false;
    }

    public static function allowsForInventoryScope(
        Club $club,
        User $user,
        string $permission,
        ?int $departmentId = null,
        ?int $teamId = null,
    ): bool {
        if ($teamId) {
            $team = Team::query()->with('club')->where('club_id', $club->id)->find($teamId);

            return $team ? self::allowsForTeam($team, $user, $permission) : false;
        }

        if ($departmentId) {
            return self::allowsInScope($club, $user, $permission, 'department', $departmentId);
        }

        return self::allows($club, $user, $permission);
    }

    public static function allowsForInventoryItem(ClubInventoryItem $item, User $user, string $permission): bool
    {
        $item->loadMissing('club');

        return $item->club
            && self::allowsForInventoryScope(
                $item->club,
                $user,
                $permission,
                $item->club_department_id ? (int) $item->club_department_id : null,
                $item->team_id ? (int) $item->team_id : null,
            );
    }

    public static function allowsAnyInventoryScope(Club $club, User $user, string $permission): bool
    {
        return self::allowsAnyScope($club, $user, $permission);
    }

    public static function allowsAnyScope(Club $club, User $user, string $permission): bool
    {
        if (self::allows($club, $user, $permission)) {
            return true;
        }

        try {
            return self::assignedScopesFor($club, $user)
                ->contains(fn ($assignment) => self::allowsInScope(
                    $club,
                    $user,
                    $permission,
                    $assignment->scope_type,
                    (int) $assignment->scope_id,
                ));
        } catch (Throwable) {
            return false;
        }
    }

    public static function allowsAnyDepartmentScope(Club $club, User $user, string $permission): bool
    {
        if (self::allows($club, $user, $permission)) {
            return true;
        }

        try {
            return self::assignedScopesFor($club, $user)
                ->where('scope_type', 'department')
                ->contains(fn ($assignment) => self::allowsInScope(
                    $club,
                    $user,
                    $permission,
                    'department',
                    (int) $assignment->scope_id,
                ));
        } catch (Throwable) {
            return false;
        }
    }

    public static function editableBy(Club $club, User $user): bool
    {
        return $club->owner_id === $user->id
            || $user->hasAnyRole(Roles::FULL_ACCESS)
            || self::allows($club, $user, self::MEMBERS_ROLES);
    }

    private static function delegatedFor(Club $club, User $user, ?array $scope): array
    {
        try {
            if (! Schema::hasTable('club_permission_delegations')) {
                return [];
            }

            return ClubPermissionDelegation::query()
                ->where('club_id', $club->id)
                ->where('grantee_user_id', $user->id)
                ->active()
                ->where(function ($query) use ($scope): void {
                    $query->where('scope_key', 'club');
                    if (is_array($scope)
                        && in_array($scope['type'] ?? null, ['department', 'team'], true)
                        && is_int($scope['id'] ?? null) && $scope['id'] > 0) {
                        $query->orWhere('scope_key', $scope['type'].':'.$scope['id']);
                    }
                })
                ->get(['permissions'])
                ->flatMap(fn (ClubPermissionDelegation $delegation) => $delegation->permissions)
                ->filter(fn ($permission) => is_string($permission) && in_array($permission, self::ALL, true))
                ->mapWithKeys(fn (string $permission) => [$permission => true])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private static function assignedScopesFor(Club $club, User $user)
    {
        $scopes = collect();

        if (Schema::hasTable('club_role_assignments')) {
            $scopes = $scopes->merge(ClubRoleAssignment::query()
                ->where('club_id', $club->id)
                ->where('user_id', $user->id)
                ->whereIn('scope_type', ['department', 'team'])
                ->get(['scope_type', 'scope_id']));
        }

        if (Schema::hasTable('club_permission_delegations')) {
            $scopes = $scopes->merge(ClubPermissionDelegation::query()
                ->where('club_id', $club->id)
                ->where('grantee_user_id', $user->id)
                ->active()
                ->whereIn('scope_type', ['department', 'team'])
                ->get(['scope_type', 'scope_id']));
        }

        return $scopes->unique(fn ($scope) => $scope->scope_type.':'.$scope->scope_id)->values();
    }

    private static function configuredRolePermissions(Club $club, User $user, ?array $scope): array
    {
        try {
            if (! Schema::hasTable('club_role_assignments') || ! Schema::hasTable('club_role_definitions')) {
                return [];
            }

            return ClubRoleAssignment::query()
                ->where('club_role_assignments.club_id', $club->id)
                ->where('club_role_assignments.user_id', $user->id)
                ->where(function ($query) use ($club, $scope) {
                    $query->whereIn('club_role_assignments.scope_key', ['club', 'club:'.$club->id]);
                    if (is_array($scope)
                        && in_array($scope['type'] ?? null, ['department', 'team'], true)
                        && is_int($scope['id'] ?? null) && $scope['id'] > 0) {
                        $query->orWhere('club_role_assignments.scope_key', $scope['type'].':'.$scope['id']);
                    }
                })
                ->join('club_role_definitions', function ($join) {
                    $join->on('club_role_definitions.id', '=', 'club_role_assignments.club_role_definition_id')
                        ->on('club_role_definitions.club_id', '=', 'club_role_assignments.club_id');
                })
                ->where('club_role_definitions.is_active', true)
                ->get(['club_role_definitions.permissions'])
                ->flatMap(function ($definition) {
                    $permissions = is_array($definition->permissions)
                        ? $definition->permissions
                        : json_decode((string) $definition->permissions, true);

                    return is_array($permissions) ? $permissions : [];
                })
                ->filter(fn ($permission) => is_string($permission) && in_array($permission, self::ALL, true))
                ->mapWithKeys(fn (string $permission) => [$permission => true])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
