<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Roles;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.manage') || $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage') || $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return ($user->can('user.manage') || $user->can('users.edit'))
            && ($user->is($model)
                || ! $model->hasAnyRole(Roles::PROTECTED_PLATFORM)
                || $user->hasRole('super_admin'));
    }

    public function delete(User $user, User $model): bool
    {
        return ! $user->is($model)
            && ($user->can('user.manage') || $user->can('users.delete'))
            && (! $model->hasAnyRole(Roles::PROTECTED_PLATFORM)
                || $user->hasRole('super_admin'));
    }

    public function assignRoles(User $user, User $model): bool
    {
        return ! $user->is($model)
            && ($user->can('user.manage') || $user->can('users.assign_roles'))
            && (! $model->hasAnyRole(Roles::PROTECTED_PLATFORM)
                || $user->hasRole('super_admin'));
    }
}
