<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserRoleAssignmentService;
use App\Support\Roles;
use Illuminate\Http\Request;

class RoleAssignmentController extends Controller
{
    public function index(Request $request, UserRoleAssignmentService $service)
    {
        $actor = $request->user();
        abort_unless($actor->can('users.assign_roles') || $actor->can('user.manage'), 403);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:255'],
            'assigned_only' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $users = User::query()->with('roles:id,name')->whereKeyNot($actor->id)
            ->when(! $actor->hasRole('super_admin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', Roles::PROTECTED_PLATFORM)))
            ->when(isset($data['user_id']), fn ($q) => $q->whereKey($data['user_id']))
            ->when(trim($data['q'] ?? '') !== '', function ($query) use ($data) {
                $term = '%'.trim($data['q']).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->boolean('assigned_only') && ! empty($data['role']), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $data['role'])->where('guard_name', 'web')))
            ->orderBy('name')->paginate(20, ['id', 'name', 'email'])->through(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'roles' => $u->roles->pluck('name')->sort()->values(),
            ]);

        return response()->json(['data' => [
            'users' => $users,
            'roles' => $service->availableRoles($actor)->map(fn ($role) => [
                'name' => $role->name, 'description' => $role->description,
                'permissions' => $role->permissions->map(fn ($p) => ['name' => $p->name, 'description' => $p->description ?: $p->name])->values(),
            ]),
        ]]);
    }

    public function update(Request $request, User $user, UserRoleAssignmentService $service)
    {
        $service->authorize($request->user(), $user);
        $data = $request->validate([
            'roles' => ['present', 'array', 'max:100'],
            'roles.*' => ['string', 'distinct', 'max:255'],
            'expected_roles' => ['present', 'array'],
            'expected_roles.*' => ['string', 'distinct', 'max:255'],
        ]);
        $service->assign($request->user(), $user, $data['roles'], $data['expected_roles']);

        return response()->json(['data' => $service->snapshot($user->fresh()), 'message' => 'Rollen gespeichert. Die Person wird über Änderungen benachrichtigt.']);
    }
}
