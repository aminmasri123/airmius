<?php

namespace App\Support;

use App\Models\User;

final class ClubWorkspaceAccess
{
    public static function isScoped(User $user): bool
    {
        $attributes = request()->attributes;
        $key = self::class.':'.$user->getKey();
        if (! $attributes->has($key)) {
            $attributes->set($key, self::resolve($user));
        }

        return $attributes->get($key);
    }

    private static function resolve(User $user): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return false;
        }

        return $user->hasAnyRole(Roles::CLUB_ADMIN)
            || $user->can('org.manage')
            || $user->can('club-cockpit.view')
            || $user->can('cockpit.view')
            || ClubPermissions::allowsAnyClub($user, [ClubPermissions::COCKPIT_VIEW]);
    }
}
