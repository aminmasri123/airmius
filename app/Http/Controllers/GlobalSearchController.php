<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $search) {}

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:'.GlobalSearchService::MAXIMUM_LENGTH],
        ]);
        $term = preg_replace('/\s+/u', ' ', trim((string) ($data['q'] ?? ''))) ?: '';

        if (mb_strlen($term) < GlobalSearchService::MINIMUM_LENGTH) {
            return $this->response($term, collect());
        }

        return $this->response($term, $this->search->search($request->user(), $term));
    }

    private function response(string $term, $results)
    {
        return response()->json([
            'results' => $results->values(),
            'meta' => [
                'query' => $term,
                'count' => $results->count(),
                'minimum_length' => GlobalSearchService::MINIMUM_LENGTH,
                'per_type_limit' => GlobalSearchService::PER_TYPE_LIMIT,
                'version' => GlobalSearchService::VERSION,
            ],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }
}
