<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AccountType
{
    public const ATHLETE = 'athlete';

    public const COACH = 'coach';

    public const CLUB = 'club';

    public const SPONSOR = 'sponsor';

    public const VALUES = [
        self::ATHLETE,
        self::COACH,
        self::CLUB,
        self::SPONSOR,
    ];

    public static function normalize(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, self::VALUES, true) ? $value : self::ATHLETE;
    }

    public static function role(string $accountType): string
    {
        return match (self::normalize($accountType)) {
            self::COACH => 'coach',
            self::CLUB => 'club_owner',
            self::SPONSOR => 'sponsor',
            default => 'player',
        };
    }

    public static function assignInitialRole(User $user, string $accountType, bool $minor): void
    {
        $role = Role::findOrCreate(
            $minor ? 'minor_pending_consent' : self::role($accountType),
            'web',
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->syncRoles([$role]);
    }
}
