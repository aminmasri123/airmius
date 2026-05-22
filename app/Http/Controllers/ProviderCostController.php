<?php

namespace App\Http\Controllers;

use App\Services\ExternalProviderUsageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderCostController extends Controller
{
    public function index(Request $request, ExternalProviderUsageService $usage): Response
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return Inertia::render('Auth/Dashboard/Admin/ProviderCosts/Index', [
            'dashboard' => $usage->dashboard($data['month'] ?? null),
        ]);
    }
}
