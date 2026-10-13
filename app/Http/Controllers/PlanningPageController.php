<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanningPageController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Auth/Dashboard/Planning/Index');
    }

    public function family(Request $request)
    {
        return Inertia::render('Auth/Dashboard/Planning/FamilyCalendar');
    }
}
