<?php

namespace App\Support;

use App\Http\Controllers\ClubMembershipController;
use App\Http\Controllers\TrainerCockpitController;
use App\Models\User;

final class NavigationModules
{
    public const ATHLETE = 'athlete';

    public const COACH = 'coach';

    public const CLUB = 'club';

    /**
     * The order here is also the order used in the settings screen.
     */
    public static function definitions(): array
    {
        return [
            self::ATHLETE => [
                'key' => self::ATHLETE,
                'label' => 'Sportler',
                'description' => 'Training, Ernährung, Events, Sportkarte und Sportpartner.',
                'icon' => 'las la-running',
            ],
            self::COACH => [
                'key' => self::COACH,
                'label' => 'Trainer / Coach',
                'description' => 'Teams, Trainingsplanung, Anwesenheit und Trainer-Cockpit.',
                'icon' => 'las la-chalkboard-teacher',
            ],
            self::CLUB => [
                'key' => self::CLUB,
                'label' => 'Verein',
                'description' => 'Vereinsverwaltung, Mitglieder, Teams, Finanzen und Organisation.',
                'icon' => 'las la-building',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Returns the role-based ceiling. Preferences may hide modules from the
     * navigation, but they can never grant a role or permission.
     */
    public static function availableFor(User $user): array
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return self::keys();
        }

        $roles = $user->getRoleNames();
        $hasClubWorkspace = $roles->intersect(Roles::CLUB_ADMIN)->isNotEmpty()
            || $user->can('org.manage')
            || $user->can('club-cockpit.view')
            || $user->can('club-memberships.view')
            || ClubMembershipController::userCanView($user);

        if ($hasClubWorkspace) {
            // A club account receives all three areas by default.
            return self::keys();
        }

        $hasCoachWorkspace = $roles->intersect([
            'coach',
            'assistant_coach',
            'performance_coach',
            'fitness_coach',
            'team_manager',
            'captain',
            'trainer',
        ])->isNotEmpty()
            || $user->can('trainer-cockpit.view')
            || TrainerCockpitController::userCanView($user);

        if ($hasCoachWorkspace) {
            // A trainer/coach also keeps the athlete area available.
            return [self::ATHLETE, self::COACH];
        }

        // Keep the personal area as the safe default for ordinary accounts.
        return [self::ATHLETE];
    }

    public static function enabledFor(User $user, ?array $available = null): array
    {
        $available ??= self::availableFor($user);
        $configured = $user->enabled_navigation_modules;

        if (! is_array($configured)) {
            return $available;
        }

        return collect($configured)
            ->prepend(self::ATHLETE)
            ->filter(fn ($module) => is_string($module) && in_array($module, $available, true))
            ->unique()
            ->values()
            ->all();
    }

    public static function sanitize(User $user, ?array $modules): array
    {
        $available = self::availableFor($user);

        return collect($modules ?? [])
            ->prepend(self::ATHLETE)
            ->filter(fn ($module) => is_string($module) && in_array($module, $available, true))
            ->unique()
            ->values()
            ->all();
    }

    public static function payload(User $user): array
    {
        $available = self::availableFor($user);

        return [
            'available' => $available,
            'enabled' => self::enabledFor($user, $available),
            'definitions' => collect(self::definitions())
                ->only($available)
                ->values()
                ->all(),
        ];
    }
}
