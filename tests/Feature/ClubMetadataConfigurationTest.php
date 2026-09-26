<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubCustomFieldDefinition;
use App\Models\ClubNumberAllocation;
use App\Models\ClubNumberRange;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMetadataConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_manages_custom_fields_and_categories_without_exposing_values_in_audit(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $field = $this->postJson("/api/v1/clubs/{$club->id}/metadata/custom-fields", [
            'entity_type' => 'member',
            'key' => 'medical_note',
            'label' => 'Vertrauliche Diagnose',
            'field_type' => 'textarea',
            'is_required' => false,
            'is_sensitive' => true,
            'is_active' => true,
            'sort_order' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.is_sensitive', true)
            ->json('data');

        $category = $this->postJson("/api/v1/clubs/{$club->id}/metadata/categories", [
            'scope' => 'member',
            'name' => 'Jugend',
            'color' => '#123ABC',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertCreated()->json('data');

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/custom-fields/{$field['id']}", [
            'entity_type' => 'member',
            'key' => 'medical_note',
            'label' => 'Gesundheitshinweis',
            'field_type' => 'textarea',
            'is_required' => false,
            'is_sensitive' => true,
            'is_active' => false,
            'sort_order' => 11,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson("/api/v1/clubs/{$club->id}/metadata")
            ->assertOk()
            ->assertJsonCount(1, 'data.custom_fields')
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.can_manage', true);

        $audits = Activity::query()->where('club_id', $club->id)->where('type', 'like', 'club.%')->get();
        $encoded = $audits->pluck('data')->toJson();
        $this->assertStringNotContainsString('Diagnose', $encoded);
        $this->assertStringNotContainsString('medical_note', $encoded);
        $this->assertStringNotContainsString('Jugend', $encoded);

        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/custom-fields/{$field['id']}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category['id']}")->assertOk();
    }

    public function test_custom_field_and_category_validation_is_scoped_and_strict(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $base = [
            'entity_type' => 'team', 'key' => 'level', 'label' => 'Stufe', 'field_type' => 'select',
            'options' => ['A', 'B'], 'is_required' => true, 'is_sensitive' => false,
            'is_active' => true, 'sort_order' => 0,
        ];
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/custom-fields", $base)->assertCreated();
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/custom-fields", $base)
            ->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/custom-fields", [
            ...$base, 'key' => 'other', 'options' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/custom-fields", [
            ...$base, 'key' => 'Bad Key',
        ])->assertUnprocessable()->assertJsonValidationErrors('key');

        $category = [
            'scope' => 'event', 'name' => 'Turnier', 'color' => '#00AA44',
            'is_active' => true, 'sort_order' => 0,
        ];
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/categories", $category)->assertCreated();
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/categories", $category)
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/categories", [
            ...$category, 'name' => 'Training', 'color' => 'green',
        ])->assertUnprocessable()->assertJsonValidationErrors('color');
    }

    public function test_only_club_management_can_access_configuration_and_cross_club_children_are_hidden(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $foreignField = ClubCustomFieldDefinition::query()->create([
            'club_id' => $otherClub->id,
            'entity_type' => 'member',
            'key' => 'foreign',
            'label' => 'Fremd',
            'field_type' => 'text',
            'is_required' => false,
            'is_sensitive' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/metadata")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/custom-fields/{$foreignField->id}")
            ->assertNotFound();
        $this->assertDatabaseHas('club_custom_field_definitions', ['id' => $foreignField->id]);
    }

    public function test_metadata_view_edit_and_delete_permissions_are_independent(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$viewer, $editor, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $club->users()->updateExistingPivot($viewer->id, [
            'permission_overrides' => [ClubPermissions::METADATA_VIEW => true],
        ]);
        $club->users()->updateExistingPivot($editor->id, [
            'permission_overrides' => [ClubPermissions::METADATA_EDIT => true],
        ]);
        $club->users()->updateExistingPivot($deleter->id, [
            'permission_overrides' => [ClubPermissions::METADATA_DELETE => true],
        ]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::METADATA_VIEW => false,
                ClubPermissions::METADATA_EDIT => false,
            ],
        ]);
        $category = $club->categories()->create([
            'scope' => 'member', 'name' => 'Bestand', 'color' => '#123ABC',
            'is_active' => true, 'sort_order' => 0,
        ]);
        $payload = [
            'scope' => 'member', 'name' => 'Neu', 'color' => '#2563EB',
            'is_active' => true, 'sort_order' => 1,
        ];

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_view_metadata', true)
            ->assertJsonPath('data.can_edit_metadata', false)
            ->assertJsonPath('data.viewer.can_view_metadata', true)
            ->assertJsonPath('data.viewer.can_edit_metadata', false);
        $this->getJson("/api/v1/clubs/{$club->id}/metadata")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false);
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/categories", $payload)->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category->id}")->assertForbidden();

        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/clubs/{$club->id}/metadata")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_view_metadata', true)
            ->assertJsonPath('data.can_edit_metadata', true)
            ->assertJsonPath('data.viewer.can_view_metadata', true)
            ->assertJsonPath('data.viewer.can_edit_metadata', true);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/metadata/categories", $payload)
            ->assertCreated()->json('data');
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/categories/{$created['id']}")->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson("/api/v1/clubs/{$club->id}/metadata")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', true);
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category->id}", $payload)
            ->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category->id}")
            ->assertOk()->assertJsonPath('data.deleted', true);

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_view_metadata', false)
            ->assertJsonPath('data.can_edit_metadata', false)
            ->assertJsonPath('data.viewer.can_view_metadata', false)
            ->assertJsonPath('data.viewer.can_edit_metadata', false);

        $clubScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/clubs_screen.dart'));
        $organizationScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/club_organization_screen.dart'));
        $webProfile = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Clubs/Profile.vue'));
        $this->assertStringContainsString('canEditMetadata: club.canEditMetadata', $clubScreen);
        $this->assertStringNotContainsString('canEditMetadata: club.canManage', $clubScreen);
        $this->assertStringNotContainsString('widget.club.canViewMetadata || widget.club.canManage', $organizationScreen);
        $this->assertStringNotContainsString('viewer.can_view_metadata || viewer.can_manage', $webProfile);
    }

    public function test_number_allocation_is_sequential_idempotent_and_audit_is_data_minimal(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $range = $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges", [
            'scope' => 'invoice',
            'name' => 'Rechnungen intern',
            'prefix' => 'RE-{YYYY}-',
            'suffix' => '',
            'padding' => 4,
            'start_number' => 7,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.preview', 'RE-2026-0007')
            ->json('data');

        $firstKey = (string) Str::uuid();
        $first = $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}/allocate", [
            'allocation_key' => $firstKey,
        ])->assertCreated()->assertJsonPath('data.formatted_number', 'RE-2026-0007')->json('data');

        $retry = $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}/allocate", [
            'allocation_key' => $firstKey,
        ])->assertOk()->json('data');
        $this->assertSame($first['id'], $retry['id']);

        $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}/allocate", [
            'allocation_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.formatted_number', 'RE-2026-0008');

        $this->assertSame(2, ClubNumberAllocation::query()->count());
        $this->assertSame(9, ClubNumberRange::query()->findOrFail($range['id'])->next_number);
        $allocationAudits = Activity::query()->where('type', 'club.number_range.allocated')->get();
        $this->assertCount(2, $allocationAudits);
        $this->assertStringNotContainsString('RE-2026', $allocationAudits->pluck('data')->toJson());

        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}")
            ->assertUnprocessable()->assertJsonValidationErrors('number_range');

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}", [
            'scope' => 'invoice',
            'name' => 'Rechnungen intern',
            'prefix' => 'NEU-{YYYY}-',
            'suffix' => '',
            'padding' => 4,
            'start_number' => 7,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('prefix');
    }

    public function test_yearly_ranges_reset_safely_and_require_a_year_token(): void
    {
        Carbon::setTestNow('2026-12-31 12:00:00');
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);
        $payload = [
            'scope' => 'receipt', 'name' => 'Belege', 'prefix' => 'B-', 'suffix' => '',
            'padding' => 3, 'start_number' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ];

        $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('reset_policy');

        $range = $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges", [
            ...$payload, 'prefix' => 'B-{YY}-',
        ])->assertCreated()->json('data');
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}/allocate", [
            'allocation_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.formatted_number', 'B-26-005');

        Carbon::setTestNow('2027-01-01 12:00:00');
        $this->postJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$range['id']}/allocate", [
            'allocation_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.formatted_number', 'B-27-005');

        $this->assertDatabaseHas('club_number_allocations', [
            'club_number_range_id' => $range['id'], 'period_key' => 2027, 'sequence_number' => 5,
        ]);
    }

    public function test_owner_assigns_exactly_one_default_per_scope_and_assignment_locks_the_range(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $first = ClubNumberRange::query()->create([
            'club_id' => $club->id, 'scope' => 'member', 'name' => 'Mitglieder A',
            'prefix' => 'A-', 'suffix' => '', 'padding' => 4, 'start_number' => 1,
            'next_number' => 1, 'reset_policy' => 'never', 'is_active' => true,
        ]);
        $second = ClubNumberRange::query()->create([
            'club_id' => $club->id, 'scope' => 'member', 'name' => 'Mitglieder B',
            'prefix' => 'B-', 'suffix' => '', 'padding' => 4, 'start_number' => 1,
            'next_number' => 1, 'reset_policy' => 'never', 'is_active' => true,
        ]);
        $foreign = ClubNumberRange::query()->create([
            'club_id' => $otherClub->id, 'scope' => 'member', 'name' => 'Fremd',
            'prefix' => 'F-', 'suffix' => '', 'padding' => 4, 'start_number' => 1,
            'next_number' => 1, 'reset_policy' => 'never', 'is_active' => true,
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$first->id}/default")
            ->assertOk()->assertJsonPath('data.scope', 'member');
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$second->id}/default")
            ->assertOk()->assertJsonPath('data.number_range_id', $second->id);
        $this->assertDatabaseCount('club_number_range_defaults', 1);
        $this->assertDatabaseHas('club_number_range_defaults', [
            'club_id' => $club->id, 'scope' => 'member', 'club_number_range_id' => $second->id,
        ]);

        $ranges = $this->getJson("/api/v1/clubs/{$club->id}/metadata")->assertOk()->json('data.number_ranges');
        $this->assertFalse(collect($ranges)->firstWhere('id', $first->id)['is_default']);
        $this->assertTrue(collect($ranges)->firstWhere('id', $second->id)['is_default']);
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$foreign->id}/default")
            ->assertNotFound();
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$second->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('number_range');
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$second->id}", [
            'scope' => 'invoice', 'name' => 'Mitglieder B', 'prefix' => 'B-', 'suffix' => '',
            'padding' => 4, 'start_number' => 1, 'reset_policy' => 'never', 'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('scope');

        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$second->id}/default")
            ->assertOk()->assertJsonPath('data.cleared', true);
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/number-ranges/{$second->id}")->assertOk();
        $this->assertSame(2, Activity::query()->where('type', 'club.number_range.default_set')->count());
        $this->assertSame(1, Activity::query()->where('type', 'club.number_range.default_cleared')->count());
    }
}
