<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubCustomFieldDefinition;
use App\Models\ClubNumberRange;
use App\Models\ClubNumberRangeDefault;
use App\Services\ClubMetadataSubjectService;
use App\Services\ClubNumberRangeService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubMetadataController extends Controller
{
    public function index(Request $request, Club $club, ClubNumberRangeService $numberRanges)
    {
        $this->authorizePermission($request, $club, ClubPermissions::METADATA_VIEW);

        $canEdit = ClubPermissions::allows($club, $request->user(), ClubPermissions::METADATA_EDIT);
        $canDelete = ClubPermissions::allows($club, $request->user(), ClubPermissions::METADATA_DELETE);

        return response()->json(['data' => [
            'custom_fields' => $club->customFieldDefinitions()
                ->orderBy('entity_type')->orderBy('sort_order')->orderBy('label')->get()
                ->map(fn (ClubCustomFieldDefinition $field) => $this->customFieldPayload($field)),
            'categories' => $club->categories()
                ->orderBy('scope')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (ClubCategory $category) => $this->categoryPayload($category)),
            'number_ranges' => $club->numberRanges()->with(['defaultAssignment'])->withCount('allocations')
                ->orderBy('scope')->orderBy('name')->get()
                ->map(fn (ClubNumberRange $range) => $this->numberRangePayload($range, $numberRanges)),
            'can_manage' => $canEdit || $canDelete,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ]]);
    }

    public function storeCustomField(Request $request, Club $club)
    {
        $this->authorizePermission($request, $club, ClubPermissions::METADATA_EDIT);
        $field = $club->customFieldDefinitions()->create($this->customFieldData($request, $club));
        $this->audit($club, $request, 'club.custom_field.created', $field, 'custom_field');

        return response()->json(['data' => $this->customFieldPayload($field)], 201);
    }

    public function updateCustomField(Request $request, Club $club, ClubCustomFieldDefinition $customField)
    {
        $this->authorizeChild($request, $club, $customField, ClubPermissions::METADATA_EDIT);
        $data = $this->customFieldData($request, $club, $customField);
        if ($customField->values()->exists()) {
            foreach (['entity_type', 'key', 'field_type', 'options'] as $lockedField) {
                $current = $lockedField === 'options'
                    ? json_encode($customField->getAttribute($lockedField) ?? [])
                    : (string) $customField->getAttribute($lockedField);
                $next = $lockedField === 'options'
                    ? json_encode($data[$lockedField] ?? [])
                    : (string) $data[$lockedField];
                if ($current !== $next) {
                    throw ValidationException::withMessages([
                        $lockedField => __('validation.metadata_field_structure_locked'),
                    ]);
                }
            }
        }
        $customField->update($data);
        $this->audit($club, $request, 'club.custom_field.updated', $customField, 'custom_field');

        return response()->json(['data' => $this->customFieldPayload($customField->refresh())]);
    }

    public function destroyCustomField(Request $request, Club $club, ClubCustomFieldDefinition $customField)
    {
        $this->authorizeChild($request, $club, $customField, ClubPermissions::METADATA_DELETE);
        if ($customField->values()->exists()) {
            throw ValidationException::withMessages(['custom_field' => __('validation.metadata_field_in_use')]);
        }
        $this->audit($club, $request, 'club.custom_field.deleted', $customField, 'custom_field');
        $customField->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeCategory(Request $request, Club $club)
    {
        $this->authorizePermission($request, $club, ClubPermissions::METADATA_EDIT);
        $category = $club->categories()->create($this->categoryData($request, $club));
        $this->audit($club, $request, 'club.category.created', $category, 'category');

        return response()->json(['data' => $this->categoryPayload($category)], 201);
    }

    public function updateCategory(Request $request, Club $club, ClubCategory $category)
    {
        $this->authorizeChild($request, $club, $category, ClubPermissions::METADATA_EDIT);
        $data = $this->categoryData($request, $club, $category);
        if ($category->assignments()->exists() && $category->scope !== $data['scope']) {
            throw ValidationException::withMessages([
                'scope' => __('validation.metadata_category_scope_locked'),
            ]);
        }
        $category->update($data);
        $this->audit($club, $request, 'club.category.updated', $category, 'category');

        return response()->json(['data' => $this->categoryPayload($category->refresh())]);
    }

    public function destroyCategory(Request $request, Club $club, ClubCategory $category)
    {
        $this->authorizeChild($request, $club, $category, ClubPermissions::METADATA_DELETE);
        if ($category->assignments()->exists()) {
            throw ValidationException::withMessages(['category' => __('validation.metadata_category_in_use')]);
        }
        $this->audit($club, $request, 'club.category.deleted', $category, 'category');
        $category->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeNumberRange(Request $request, Club $club, ClubNumberRangeService $numberRanges)
    {
        $this->authorizePermission($request, $club, ClubPermissions::METADATA_EDIT);
        $data = $this->numberRangeData($request, $club);
        $data['next_number'] = $data['start_number'];
        $range = $club->numberRanges()->create($data);
        $this->audit($club, $request, 'club.number_range.created', $range, 'number_range');

        return response()->json(['data' => $this->numberRangePayload($range->loadCount('allocations'), $numberRanges)], 201);
    }

    public function updateNumberRange(
        Request $request,
        Club $club,
        ClubNumberRange $numberRange,
        ClubNumberRangeService $numberRanges
    ) {
        $this->authorizeChild($request, $club, $numberRange, ClubPermissions::METADATA_EDIT);
        $data = $this->numberRangeData($request, $club, $numberRange);
        $hasAllocations = $numberRange->allocations()->exists();
        $isDefault = $numberRange->defaultAssignment()->exists();
        if ($isDefault && $numberRange->scope !== $data['scope']) {
            throw ValidationException::withMessages([
                'scope' => __('validation.number_range_default_scope_locked'),
            ]);
        }
        if ($hasAllocations) {
            foreach (['scope', 'prefix', 'suffix', 'padding', 'start_number', 'reset_policy'] as $lockedField) {
                if ((string) $numberRange->getAttribute($lockedField) !== (string) $data[$lockedField]) {
                    throw ValidationException::withMessages([
                        $lockedField => __('validation.number_range_format_locked'),
                    ]);
                }
            }
        } else {
            $data['next_number'] = $data['start_number'];
        }
        $numberRange->update($data);
        $this->audit($club, $request, 'club.number_range.updated', $numberRange, 'number_range');

        return response()->json([
            'data' => $this->numberRangePayload($numberRange->refresh()->loadCount('allocations'), $numberRanges),
        ]);
    }

    public function destroyNumberRange(Request $request, Club $club, ClubNumberRange $numberRange)
    {
        $this->authorizeChild($request, $club, $numberRange, ClubPermissions::METADATA_DELETE);
        if ($numberRange->allocations()->exists() || $numberRange->defaultAssignment()->exists()) {
            throw ValidationException::withMessages(['number_range' => __('validation.number_range_in_use')]);
        }
        $this->audit($club, $request, 'club.number_range.deleted', $numberRange, 'number_range');
        $numberRange->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function allocateNumber(
        Request $request,
        Club $club,
        ClubNumberRange $numberRange,
        ClubNumberRangeService $numberRanges
    ) {
        $this->authorizeChild($request, $club, $numberRange, ClubPermissions::METADATA_EDIT);
        $data = $request->validate(['allocation_key' => ['required', 'uuid']]);
        $allocation = $numberRanges->allocate($numberRange, $request->user(), $data['allocation_key']);

        return response()->json(['data' => [
            'id' => $allocation->id,
            'number_range_id' => $allocation->club_number_range_id,
            'formatted_number' => $allocation->formatted_number,
            'allocation_key' => $allocation->allocation_key,
            'created_at' => $allocation->created_at?->toJSON(),
        ]], $allocation->wasRecentlyCreated ? 201 : 200);
    }

    public function setDefaultNumberRange(Request $request, Club $club, ClubNumberRange $numberRange)
    {
        $this->authorizeChild($request, $club, $numberRange, ClubPermissions::METADATA_EDIT);
        if (! $numberRange->is_active) {
            throw ValidationException::withMessages(['number_range' => __('validation.number_range_inactive')]);
        }
        DB::transaction(function () use ($club, $numberRange, $request) {
            Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            ClubNumberRangeDefault::query()->updateOrCreate(
                ['club_id' => $club->id, 'scope' => $numberRange->scope],
                ['club_number_range_id' => $numberRange->id, 'assigned_by' => $request->user()->id]
            );
            $this->audit($club, $request, 'club.number_range.default_set', $numberRange, 'number_range');
        });

        return response()->json(['data' => ['number_range_id' => $numberRange->id, 'scope' => $numberRange->scope]]);
    }

    public function clearDefaultNumberRange(Request $request, Club $club, ClubNumberRange $numberRange)
    {
        $this->authorizeChild($request, $club, $numberRange, ClubPermissions::METADATA_EDIT);
        DB::transaction(function () use ($club, $numberRange, $request) {
            Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $assignment = $club->numberRangeDefaults()
                ->where('scope', $numberRange->scope)
                ->where('club_number_range_id', $numberRange->id)
                ->firstOrFail();
            $assignment->delete();
            $this->audit($club, $request, 'club.number_range.default_cleared', $numberRange, 'number_range');
        });

        return response()->json(['data' => ['cleared' => true]]);
    }

    public function showSubject(
        Request $request,
        Club $club,
        string $subjectType,
        int $subjectId,
        ClubMetadataSubjectService $subjects
    ) {
        $this->authorizePermission($request, $club, ClubPermissions::METADATA_VIEW);
        $subject = $subjects->resolve($club, $subjectType, $subjectId);

        return response()->json(['data' => $subjects->payload($club, $subjectType, $subject)]);
    }

    public function updateSubject(
        Request $request,
        Club $club,
        string $subjectType,
        int $subjectId,
        ClubMetadataSubjectService $subjects
    ) {
        $this->authorizePermission($request, $club, ClubPermissions::METADATA_EDIT);
        $data = $request->validate([
            'values' => ['present', 'array'],
            'category_ids' => ['present', 'array', 'max:100'],
            'category_ids.*' => ['integer', 'distinct', 'min:1'],
        ]);
        $subject = $subjects->resolve($club, $subjectType, $subjectId);
        $payload = $subjects->sync(
            $club,
            $subjectType,
            $subject,
            $request->user(),
            $data['values'],
            $data['category_ids']
        );

        return response()->json(['data' => $payload]);
    }

    private function authorizePermission(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeChild(Request $request, Club $club, Model $model, string $permission): void
    {
        $this->authorizePermission($request, $club, $permission);
        abort_unless((int) $model->getAttribute('club_id') === (int) $club->id, 404);
    }

    private function customFieldData(
        Request $request,
        Club $club,
        ?ClubCustomFieldDefinition $field = null
    ): array {
        $request->merge([
            'key' => strtolower(trim((string) $request->input('key'))),
            'label' => trim((string) $request->input('label')),
            'options' => is_array($request->input('options'))
                ? array_map(fn ($option) => is_string($option) ? trim($option) : $option, $request->input('options'))
                : $request->input('options'),
        ]);
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(ClubCustomFieldDefinition::ENTITY_TYPES)],
            'key' => [
                'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('club_custom_field_definitions')->where(fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->where('entity_type', $request->input('entity_type')))->ignore($field),
            ],
            'label' => ['required', 'string', 'max:160'],
            'field_type' => ['required', Rule::in(ClubCustomFieldDefinition::FIELD_TYPES)],
            'options' => [Rule::requiredIf($request->input('field_type') === 'select'), 'nullable', 'array', 'max:50'],
            'options.*' => ['required', 'string', 'max:160', 'distinct'],
            'is_required' => ['required', 'boolean'],
            'is_sensitive' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $data['options'] = $data['field_type'] === 'select'
            ? array_values(array_map(fn ($option) => trim($option), $data['options']))
            : null;

        return $data;
    }

    private function categoryData(Request $request, Club $club, ?ClubCategory $category = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'scope' => ['required', Rule::in(ClubCategory::SCOPES)],
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('club_categories')->where(fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->where('scope', $request->input('scope')))->ignore($category),
            ],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    private function numberRangeData(Request $request, Club $club, ?ClubNumberRange $range = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'prefix' => trim((string) $request->input('prefix', '')),
            'suffix' => trim((string) $request->input('suffix', '')),
        ]);
        $data = $request->validate([
            'scope' => ['required', Rule::in(ClubNumberRange::SCOPES)],
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('club_number_ranges')->where(fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->where('scope', $request->input('scope')))->ignore($range),
            ],
            'prefix' => ['present', 'string', 'max:40', 'regex:/^(?:[^{}]|\{YYYY\}|\{YY\})*$/'],
            'suffix' => ['present', 'string', 'max:40', 'regex:/^(?:[^{}]|\{YYYY\}|\{YY\})*$/'],
            'padding' => ['required', 'integer', 'min:1', 'max:12'],
            'start_number' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'reset_policy' => ['required', Rule::in(ClubNumberRange::RESET_POLICIES)],
            'is_active' => ['required', 'boolean'],
        ]);
        if (
            $data['reset_policy'] === 'yearly'
            && ! str_contains($data['prefix'].$data['suffix'], '{YYYY}')
            && ! str_contains($data['prefix'].$data['suffix'], '{YY}')
        ) {
            throw ValidationException::withMessages(['reset_policy' => __('validation.number_range_year_token_required')]);
        }

        return $data;
    }

    private function customFieldPayload(ClubCustomFieldDefinition $field): array
    {
        return $field->only([
            'id', 'entity_type', 'key', 'label', 'field_type', 'options', 'is_required',
            'is_sensitive', 'is_active', 'sort_order',
        ]);
    }

    private function categoryPayload(ClubCategory $category): array
    {
        return $category->only(['id', 'scope', 'name', 'color', 'is_active', 'sort_order']);
    }

    private function numberRangePayload(ClubNumberRange $range, ClubNumberRangeService $numberRanges): array
    {
        return [
            ...$range->only([
                'id', 'scope', 'name', 'prefix', 'suffix', 'padding', 'start_number', 'next_number',
                'reset_policy', 'last_reset_year', 'is_active',
            ]),
            'preview' => $numberRanges->preview($range),
            'allocations_count' => (int) ($range->allocations_count ?? $range->allocations()->count()),
            'is_default' => $range->relationLoaded('defaultAssignment')
                ? $range->defaultAssignment !== null
                : $range->defaultAssignment()->exists(),
        ];
    }

    private function audit(Club $club, Request $request, string $type, Model $subject, string $entityType): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $subject, ['entity_type' => $entityType]);
    }
}
