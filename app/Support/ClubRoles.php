<?php

namespace App\Support;

class ClubRoles
{
    public const ALL = [
        'owner',
        'admin',
        'manager',
        'academy_manager',
        'financial_controller',
        'trainer',
        'member',
    ];

    public const ELEVATED = [
        'owner',
        'admin',
        'manager',
        'academy_manager',
        'financial_controller',
    ];

    public static function normalize(?string $role, mixed $roles): array
    {
        $roles = is_array($roles) ? $roles : [];

        if ($role) {
            $roles[] = $role;
        }

        $roles = collect($roles)
            ->filter(fn ($value) => is_string($value) && in_array($value, self::ALL, true))
            ->unique()
            ->values()
            ->all();

        return $roles ?: ['member'];
    }

    public static function primary(array $roles): string
    {
        foreach (self::ALL as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'member';
    }

    public static function whereAny($query, array $roles, string $table = 'club_user')
    {
        return $query->where(function ($roleQuery) use ($roles, $table) {
            $roleQuery->whereIn($table.'.role', $roles);

            foreach ($roles as $role) {
                $roleQuery->orWhere($table.'.roles', 'like', '%"'.$role.'"%');
            }
        });
    }
}
