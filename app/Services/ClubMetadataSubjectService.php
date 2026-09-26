<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubCategoryAssignment;
use App\Models\ClubCustomFieldDefinition;
use App\Models\ClubCustomFieldValue;
use App\Models\Event;
use App\Models\User;
use App\Support\ClubAuditLog;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubMetadataSubjectService
{
    public const SUBJECT_TYPES = ['member', 'external_member', 'team', 'event', 'inventory_item'];

    public function resolve(Club $club, string $subjectType, int $subjectId): Model
    {
        abort_unless(in_array($subjectType, self::SUBJECT_TYPES, true), 404);

        return match ($subjectType) {
            'member' => $club->users()->where('users.id', $subjectId)->firstOrFail(),
            'external_member' => $club->externalMembers()->whereKey($subjectId)->firstOrFail(),
            'team' => $club->teams()->whereKey($subjectId)->firstOrFail(),
            'event' => Event::query()->whereKey($subjectId)
                ->where(function ($query) use ($club) {
                    $query->where('club_id', $club->id)
                        ->orWhereHas('team', fn ($team) => $team->where('club_id', $club->id));
                })->firstOrFail(),
            'inventory_item' => $club->inventoryItems()->whereKey($subjectId)->firstOrFail(),
        };
    }

    public function payload(Club $club, string $subjectType, Model $subject): array
    {
        $entityType = $this->entityType($subjectType);
        $subjectId = (int) $subject->getKey();
        $values = $club->customFieldValues()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->get()
            ->keyBy('club_custom_field_definition_id');
        $valueDefinitionIds = $values->keys()->all();
        $fields = $club->customFieldDefinitions()
            ->where('entity_type', $entityType)
            ->where(function ($query) use ($valueDefinitionIds) {
                $query->where('is_active', true);
                if ($valueDefinitionIds !== []) {
                    $query->orWhereIn('id', $valueDefinitionIds);
                }
            })
            ->orderBy('sort_order')->orderBy('label')->get();

        $assignments = $club->categoryAssignments()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->pluck('club_category_id');
        $assignedIds = $assignments->all();
        $categories = $club->categories()
            ->where('scope', $entityType)
            ->where(function ($query) use ($assignedIds) {
                $query->where('is_active', true);
                if ($assignedIds !== []) {
                    $query->orWhereIn('id', $assignedIds);
                }
            })
            ->orderBy('sort_order')->orderBy('name')->get();

        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'entity_type' => $entityType,
            'fields' => $fields->map(function (ClubCustomFieldDefinition $field) use ($values) {
                $stored = $values->get($field->id);

                return [
                    ...$field->only([
                        'id', 'key', 'label', 'field_type', 'options', 'is_required',
                        'is_sensitive', 'is_active', 'sort_order',
                    ]),
                    'value' => $stored?->payload['value'] ?? null,
                ];
            })->values(),
            'categories' => $categories->map(fn ($category) => [
                ...$category->only(['id', 'name', 'color', 'is_active', 'sort_order']),
                'selected' => $assignments->contains($category->id),
            ])->values(),
        ];
    }

    public function sync(
        Club $club,
        string $subjectType,
        Model $subject,
        User $actor,
        array $inputValues,
        array $categoryIds
    ): array {
        $entityType = $this->entityType($subjectType);
        $subjectId = (int) $subject->getKey();
        $definitions = $club->customFieldDefinitions()
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
        $normalized = [];

        foreach ($inputValues as $definitionId => $value) {
            if (! ctype_digit((string) $definitionId) || ! $definitions->has((int) $definitionId)) {
                throw ValidationException::withMessages(['values' => __('validation.metadata_field_invalid')]);
            }
            $normalized[(int) $definitionId] = $this->normalizeValue($definitions->get((int) $definitionId), $value);
        }
        foreach ($definitions as $definition) {
            if ($definition->is_required && (! array_key_exists($definition->id, $normalized) || $normalized[$definition->id] === null)) {
                throw ValidationException::withMessages([
                    'values.'.$definition->id => __('validation.metadata_field_required'),
                ]);
            }
        }

        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        $activeCategories = $club->categories()
            ->where('scope', $entityType)
            ->where('is_active', true)
            ->whereIn('id', $categoryIds)
            ->pluck('id')->all();
        sort($categoryIds);
        sort($activeCategories);
        if ($categoryIds !== $activeCategories) {
            throw ValidationException::withMessages(['category_ids' => __('validation.metadata_category_invalid')]);
        }

        DB::transaction(function () use (
            $club, $subjectType, $subjectId, $entityType, $subject, $actor,
            $definitions, $normalized, $categoryIds
        ) {
            $activeDefinitionIds = $definitions->keys()->all();
            if ($activeDefinitionIds !== []) {
                $club->customFieldValues()
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->whereIn('club_custom_field_definition_id', $activeDefinitionIds)
                    ->delete();
            }
            foreach ($normalized as $definitionId => $value) {
                if ($value === null) {
                    continue;
                }
                ClubCustomFieldValue::query()->create([
                    'club_id' => $club->id,
                    'club_custom_field_definition_id' => $definitionId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'payload' => ['value' => $value],
                    'updated_by' => $actor->id,
                ]);
            }

            $activeCategoryIds = $club->categories()
                ->where('scope', $entityType)->where('is_active', true)->pluck('id');
            if ($activeCategoryIds->isNotEmpty()) {
                $club->categoryAssignments()
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->whereIn('club_category_id', $activeCategoryIds)
                    ->delete();
            }
            foreach ($categoryIds as $categoryId) {
                ClubCategoryAssignment::query()->create([
                    'club_id' => $club->id,
                    'club_category_id' => $categoryId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'assigned_by' => $actor->id,
                ]);
            }

            ClubAuditLog::record(
                $club,
                $actor,
                'club.metadata_subject.updated',
                $subject,
                ['entity_type' => $entityType]
            );
        });

        return $this->payload($club, $subjectType, $subject);
    }

    public function clear(Club $club, string $subjectType, int $subjectId): void
    {
        abort_unless(in_array($subjectType, self::SUBJECT_TYPES, true), 404);
        $club->customFieldValues()
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)->delete();
        $club->categoryAssignments()
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)->delete();
    }

    private function entityType(string $subjectType): string
    {
        return match ($subjectType) {
            'member', 'external_member' => 'member',
            'team' => 'team',
            'event' => 'event',
            'inventory_item' => 'inventory_item',
            default => abort(404),
        };
    }

    private function normalizeValue(ClubCustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return match ($definition->field_type) {
            'text' => $this->stringValue($definition, $value, 1000),
            'textarea' => $this->stringValue($definition, $value, 10000),
            'number' => $this->numberValue($definition, $value),
            'date' => $this->dateValue($definition, $value),
            'boolean' => $this->booleanValue($definition, $value),
            'select' => $this->selectValue($definition, $value),
            default => throw ValidationException::withMessages([
                'values.'.$definition->id => __('validation.metadata_field_invalid'),
            ]),
        };
    }

    private function stringValue(ClubCustomFieldDefinition $definition, mixed $value, int $maxLength): string
    {
        if (! is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            $this->invalidValue($definition);
        }

        return trim($value);
    }

    private function numberValue(ClubCustomFieldDefinition $definition, mixed $value): string
    {
        $normalized = is_int($value) || is_float($value) ? (string) $value : trim((string) $value);
        if (! preg_match('/^-?\d{1,12}(?:\.\d{1,4})?$/', $normalized)) {
            $this->invalidValue($definition);
        }

        return $normalized;
    }

    private function dateValue(ClubCustomFieldDefinition $definition, mixed $value): string
    {
        if (! is_string($value)) {
            $this->invalidValue($definition);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            $this->invalidValue($definition);
        }

        return $value;
    }

    private function booleanValue(ClubCustomFieldDefinition $definition, mixed $value): bool
    {
        if (! is_bool($value)) {
            $this->invalidValue($definition);
        }

        return $value;
    }

    private function selectValue(ClubCustomFieldDefinition $definition, mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, $definition->options ?? [], true)) {
            $this->invalidValue($definition);
        }

        return $value;
    }

    private function invalidValue(ClubCustomFieldDefinition $definition): never
    {
        throw ValidationException::withMessages([
            'values.'.$definition->id => __('validation.metadata_field_value_invalid'),
        ]);
    }
}
