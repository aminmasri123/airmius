<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->select(['id', 'name', 'email', 'created_at'])
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
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        $canManageRoles = $viewer->can('user.manage') || $viewer->can('users.assign_roles');
        $profileVisible = $user->isProfileVisibleTo($viewer);

        return Inertia::render('Auth/Dashboard/Users/Profile', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $profileVisible || $canManageRoles ? $user->email : null,
                'bio' => $profileVisible ? $user->bio : null,
                'profile_visibility' => $user->profile_visibility,
                'profile_photo_url' => $user->profile_photo_url,
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'roles' => $canManageRoles ? $user->getRoleNames()->values()->all() : [],
                'permissions' => $canManageRoles
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'viewer' => [
                'is_self' => $viewer->is($user),
                'is_following' => $user->isFollowedBy($viewer),
                'can_follow' => ! $viewer->is($user) && $viewer->can('follow.user'),
                'can_manage_roles' => $canManageRoles,
                'can_view_private_profile' => $profileVisible,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
