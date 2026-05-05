<?php

namespace App\Http\Controllers;

use App\Models\UserBadge;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserBadgeController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Auth/Dashboard/Badges/UserIndex', [
            'awards' => $request->user()
                ->badgeAwards()
                ->with('badge:id,key,name,description,icon,actor_type,trigger,threshold')
                ->latest('id')
                ->get(),
        ]);
    }

    public function show(UserBadge $userBadge)
    {
        abort_unless($userBadge->user_id === request()->user()->id || request()->user()->can('system.manage'), 403);

        return Inertia::render('Auth/Dashboard/Badges/Show', [
            'award' => $userBadge->load('badge'),
        ]);
    }
}
