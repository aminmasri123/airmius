<?php

namespace App\Http\Controllers;

use App\Support\WorkAutomationAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkAutomationPageController extends Controller
{
    public function index(Request $request)
    {
        $clubs = WorkAutomationAccess::clubs($request->user());
        abort_if($clubs->isEmpty(), 403);

        return Inertia::render('Auth/Dashboard/WorkAutomation/Index', ['clubs' => $clubs]);
    }
}
