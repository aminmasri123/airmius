<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->select(['id', 'name', 'email', 'profile_visibility', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Auth/Dashboard/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return Inertia::render('Auth/Dashboard/Users/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'profile_visibility' => ['nullable', Rule::in(['public', 'private'])],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_visibility' => $request->input('profile_visibility', 'public'),
        ]);

        return redirect()->route('members.index')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $canManageRoles = request()->user()->can('assignRoles', $user);

        return Inertia::render('Auth/Dashboard/Users/Edit', [
            'user' => array_merge($user->only([
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'birth_date',
                'profile_visibility',
                'bio',
                'account_status',
                'suspended_until',
                'suspension_reason',
            ]), [
                'birth_date' => $user->birth_date?->toDateString(),
                'suspended_until' => $user->suspended_until?->toDateTimeString(),
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ]),
            'availableRoles' => $canManageRoles
                ? Role::query()->orderBy('name')->pluck('name')->values()
                : [],
            'availablePermissions' => $canManageRoles
                ? Permission::query()->orderBy('name')->pluck('name')->values()
                : [],
            'canManageRoles' => $canManageRoles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $canManageRoles = $request->user()->can('assignRoles', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'profile_visibility' => ['required', Rule::in(['public', 'private'])],
            'bio' => ['nullable', 'string', 'max:1000'],
            'suspension_action' => ['nullable', Rule::in(['', 'lift', '1', '3', '7', '10', '14', '30', '60', '90'])],
            'suspension_reason' => ['nullable', 'string', 'max:500'],
            'roles' => [$canManageRoles ? 'array' : 'prohibited'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $displayName = trim($firstName.' '.$lastName);

        $user->update([
            'name' => $displayName !== '' ? $displayName : $data['name'],
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $data['email'],
            'birth_date' => isset($data['birth_date']) && $data['birth_date'] !== ''
                ? Carbon::parse($data['birth_date'])->toDateString()
                : null,
            'profile_visibility' => $data['profile_visibility'],
            'bio' => $data['bio'] ?? null,
        ]);

        if (($data['suspension_action'] ?? '') === 'lift') {
            $user->forceFill([
                'account_status' => 'active',
                'suspended_until' => null,
                'suspension_reason' => null,
            ])->save();
        } elseif (in_array((string) ($data['suspension_action'] ?? ''), ['1', '3', '7', '10', '14', '30', '60', '90'], true)) {
            $user->forceFill([
                'account_status' => 'suspended',
                'suspended_until' => now()->addDays((int) $data['suspension_action']),
                'suspension_reason' => $data['suspension_reason'] ?? null,
            ])->save();
        }

        if ($canManageRoles && array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles']);
        }

        return redirect()->route('members.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('members.index')->with('success', 'User deleted successfully.');
    }
}
