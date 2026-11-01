<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserRoleAssignmentService
{
    public function authorize(User $actor, User $target): void
    {
        abort_unless(! $actor->is($target)
            && ($actor->can('users.assign_roles') || $actor->can('user.manage'))
            && (! $target->hasAnyRole(Roles::PROTECTED_PLATFORM) || $actor->hasRole('super_admin')), 403);
    }

    public function availableRoles(User $actor)
    {
        return Role::query()->where('guard_name', 'web')
            ->when(! $actor->hasRole('super_admin'), fn ($q) => $q->whereNotIn('name', Roles::PROTECTED_PLATFORM))
            ->with('permissions:id,name,description')->orderBy('name')->get();
    }

    public function assign(User $actor, User $target, array $names, ?array $expected = null): void
    {
        DB::transaction(function () use ($actor, $target, $names, $expected) {
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $this->authorize($actor, $target);
            $before = $this->snapshot($target);
            if ($expected !== null && $this->sorted($expected) !== $before['roles']) {
                abort(409, 'Die Rollen wurden zwischenzeitlich geändert. Bitte neu laden.');
            }
            $names = $this->sorted($names);
            $allowed = $this->availableRoles($actor)->pluck('name')->all();
            if (array_diff($names, $allowed) !== []) {
                throw ValidationException::withMessages(['roles' => 'Mindestens eine Rolle darf nicht zugewiesen werden.']);
            }
            // Delegated role managers may not grant permissions they do not hold.
            if (! $actor->hasAnyRole(Roles::FULL_ACCESS)) {
                $added = array_diff($names, $before['roles']);
                $permissions = Role::whereIn('name', $added)->where('guard_name', 'web')->with('permissions')->get()
                    ->flatMap(fn ($role) => $role->permissions->pluck('name'))->unique()->all();
                abort_if(array_diff($permissions, $actor->getAllPermissions()->pluck('name')->all()) !== [], 403);
            }
            if ($names === $before['roles']) {
                return;
            }
            $target->syncRoles(Role::where('guard_name', 'web')->whereIn('name', $names)->get());
            $this->notifyChange($target, $before);
        });
    }

    public function changePermissions(Role $role, array $permissions): void
    {
        DB::transaction(function () use ($role, $permissions) {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            if ($this->sorted($permissions) === $this->sorted($role->permissions->pluck('name')->all())) {
                return;
            }
            $before = [];
            $role->users()->chunkById(100, function ($users) use (&$before) {
                foreach ($users as $user) {
                    $before[$user->id] = $this->snapshot($user);
                }
            });
            $role->syncPermissions($permissions);
            $role->users()->chunkById(100, function ($users) use ($before) {
                foreach ($users as $user) {
                    $this->notifyChange($user, $before[$user->id]);
                }
            });
        });
    }

    public function snapshot(User $user): array
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [
            'roles' => $this->sorted($user->getRoleNames()->all()),
            'permissions' => $user->getAllPermissions()->sortBy('name')->map(fn ($p) => [
                'name' => $p->name,
                'description' => $p->description ?: $p->name,
            ])->values()->all(),
        ];
    }

    private function notifyChange(User $user, array $before): void
    {
        $after = $this->snapshot($user);
        if ($before === $after) {
            return;
        }
        $old = array_column($before['permissions'], 'name');
        $new = array_column($after['permissions'], 'name');
        $added = array_values(array_filter($after['permissions'], fn ($p) => ! in_array($p['name'], $old, true)));
        $removed = array_values(array_filter($before['permissions'], fn ($p) => ! in_array($p['name'], $new, true)));
        $describe = fn ($items) => implode('; ', array_column($items, 'description')) ?: 'Keine';
        $body = 'Deine aktuellen Rollen: '.(implode(', ', $after['roles']) ?: 'Keine').".\n"
            .'Hinzugefügte Rollen: '.(implode(', ', array_diff($after['roles'], $before['roles'])) ?: 'Keine').".\n"
            .'Entfernte Rollen: '.(implode(', ', array_diff($before['roles'], $after['roles'])) ?: 'Keine').".\n"
            .'Neue Berechtigungen: '.$describe($added).".\n"
            .'Entfallene Berechtigungen: '.$describe($removed).".\n"
            .'Deine aktuellen Berechtigungen: '.$describe($after['permissions']).".\n"
            .'Der Zugriff auf Vereins- und Teamdaten richtet sich zusätzlich nach deiner Mitgliedschaft und dem jeweiligen Kontext.';
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            $body .= "\nDeine Administratorrolle gewährt zusätzlich umfassenden Plattformzugriff.";
        }
        $requiresSetup = \App\Support\AdminTwoFactor::missingFor($user);
        if ($requiresSetup) {
            $body .= "\nWichtig: Deine Adminrolle ist gespeichert. Adminfunktionen bleiben ausgeblendet oder gesperrt, bis du unter Einstellungen → Sicherheit die Zwei-Faktor-Authentifizierung eingerichtet und bestätigt hast. Danach findest du die Verwaltungsfunktionen im Bereich Admin.";
        }
        AppNotification::send($user, 'security.roles_changed', [
            'title' => 'Deine Rollen und Berechtigungen wurden geändert',
            'body' => $body,
            'roles' => $after['roles'],
            'permissions' => $after['permissions'],
            'added_permissions' => $added,
            'removed_permissions' => $removed,
            'admin_two_factor_setup_required' => $requiresSetup,
        ], ['category' => 'security', 'priority' => 'high', 'bypass_preferences' => true, 'after_commit' => true]);
    }

    private function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
