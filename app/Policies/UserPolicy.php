<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.manage') || $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model)
            || $model->isProfileVisibleTo($user)
            || $user->can('user.manage')
            || $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage') || $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user.manage')
            || $user->can('users.edit');
    }

    public function delete(User $user, User $model): bool
    {
        return ! $user->is($model)
            && ($user->can('user.manage') || $user->can('users.delete'));
    }

    public function assignRoles(User $user, User $model): bool
    {
        return ! $user->is($model)
            && ($user->can('user.manage') || $user->can('users.assign_roles'));
    }
}
