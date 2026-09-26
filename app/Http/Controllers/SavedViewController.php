<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavedViewController extends Controller
{
    public const WORKSPACES = [
        'global_search',
        'members',
        'events',
        'invoices',
        'files',
    ];

    public const MAX_PER_WORKSPACE = 25;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', Rule::in(self::WORKSPACES)],
        ]);

        $views = SavedView::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace', $validated['workspace'])
            ->orderByDesc('is_favorite')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (SavedView $view): array => $this->payload($view));

        return response()->json(['data' => $views]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $count = SavedView::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace', $validated['workspace'])
            ->count();

        if ($count >= self::MAX_PER_WORKSPACE) {
            throw ValidationException::withMessages([
                'name' => __('saved_views.validation.limit', ['limit' => self::MAX_PER_WORKSPACE]),
            ]);
        }

        $view = SavedView::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->payload($view)], 201);
    }

    public function update(Request $request, SavedView $savedView): JsonResponse
    {
        $this->ensureOwner($request, $savedView);
        $validated = $this->validated($request, $savedView);
        $savedView->update($validated);

        return response()->json(['data' => $this->payload($savedView->fresh())]);
    }

    public function destroy(Request $request, SavedView $savedView): JsonResponse
    {
        $this->ensureOwner($request, $savedView);
        $savedView->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SavedView $savedView = null): array
    {
        $userId = (int) $request->user()->id;
        $workspace = (string) $request->input('workspace', $savedView?->workspace);

        $validated = $request->validate([
            'workspace' => ['required', 'string', Rule::in(self::WORKSPACES)],
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('saved_views')->where(fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('workspace', $workspace))
                    ->ignore($savedView?->id),
            ],
            'configuration' => ['required', 'array', 'max:20'],
            'configuration.query' => ['nullable', 'string', 'max:100'],
            'configuration.types' => ['nullable', 'array', 'max:10'],
            'configuration.types.*' => ['string', Rule::in(['user', 'club', 'team', 'event', 'course', 'product', 'file', 'invoice', 'module'])],
            'configuration.filters' => ['nullable', 'array', 'max:20'],
            'configuration.sort' => ['nullable', 'string', 'max:40'],
            'is_favorite' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $encoded = json_encode($validated['configuration'], JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 8192) {
            throw ValidationException::withMessages([
                'configuration' => __('saved_views.validation.configuration_size'),
            ]);
        }

        return $validated;
    }

    private function ensureOwner(Request $request, SavedView $savedView): void
    {
        abort_unless((int) $savedView->user_id === (int) $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function payload(SavedView $view): array
    {
        return [
            'id' => $view->id,
            'workspace' => $view->workspace,
            'name' => $view->name,
            'configuration' => $view->configuration ?? [],
            'is_favorite' => (bool) $view->is_favorite,
            'sort_order' => (int) $view->sort_order,
            'created_at' => $view->created_at?->toJSON(),
            'updated_at' => $view->updated_at?->toJSON(),
        ];
    }
}
