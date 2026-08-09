<?php

namespace App\Http\Controllers;

use App\Services\CommerceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicCommerceCatalogController extends Controller
{
    public function __invoke(Request $request, CommerceCatalogService $catalog): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', Rule::in(CommerceCatalogService::KINDS)],
            'sport' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'size:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json($catalog->discover($filters));
    }
}
