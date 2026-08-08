<?php

namespace App\Http\Controllers;

use App\Services\ProductAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductAnalyticsController extends Controller
{
    public function index(Request $request, ProductAnalyticsService $analytics): Response
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', Rule::in(config('product_analytics.allowed_windows', [7, 28, 90]))],
        ]);

        return Inertia::render('Auth/Dashboard/Admin/ProductAnalytics/Index', [
            'dashboard' => $analytics->dashboard(isset($data['days']) ? (int) $data['days'] : null),
        ]);
    }
}
