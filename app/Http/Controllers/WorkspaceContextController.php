<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\WorkspaceContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceContextController extends Controller
{
    public function __construct(private WorkspaceContextService $workspaces) {}

    public function selectClub(Request $request, Club $club): RedirectResponse
    {
        $this->workspaces->selectClub($request, $club);

        return back()->with('success', __('Arbeitsbereich gewechselt.'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->workspaces->clear($request);

        return back()->with('success', __('Persönlicher Arbeitsbereich aktiviert.'));
    }
}
