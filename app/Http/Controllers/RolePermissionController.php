<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess($request);

        $roles = Role::query()
            ->with('permissions:id,name,description')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions
                    ->sortBy('name')
                    ->values()
                    ->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'description' => $permission->description,
                    ]),
                'users_count' => $role->users_count,
                'is_system' => in_array($role->name, ['super_admin', 'admin', 'system_admin'], true),
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString())
            ->map(fn ($items, $group) => [
                'group' => $group,
                'items' => $items->values(),
            ])
            ->values();

        return Inertia::render('Auth/Dashboard/RolesPermissions/Index', [
            'roles' => $roles,
            'permissionGroups' => $permissions,
        ]);
    }

    public function storeRole(Request $request)
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Rolle erstellt.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update([
            'description' => $data['description'] ?? null,
        ]);
        $role->syncPermissions($data['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Rolle aktualisiert.');
    }

    public function destroyRole(Request $request, Role $role)
    {
        $this->authorizeAccess($request);

        abort_if(in_array($role->name, ['super_admin', 'admin', 'system_admin'], true), 422, 'Systemrollen können nicht geloescht werden.');
        abort_if($role->users()->exists(), 422, 'Rolle ist noch Nutzern zugewiesen.');

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Rolle geloescht.');
    }

    public function storePermission(Request $request)
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('permissions', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Permission::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Berechtigung erstellt.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless(
            $request->user()->can('user.manage') || $request->user()->can('users.assign_roles'),
            403
        );
    }
}
