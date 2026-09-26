<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubCustomFieldDefinition;
use App\Models\ClubExternalMember;
use App\Models\ClubInventoryItem;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMetadataValuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_saves_and_reads_every_supported_field_type_and_categories(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $definitions = collect([
            ['key' => 'short', 'field_type' => 'text', 'is_required' => true],
            ['key' => 'long', 'field_type' => 'textarea'],
            ['key' => 'score', 'field_type' => 'number'],
            ['key' => 'reviewed_on', 'field_type' => 'date'],
            ['key' => 'approved', 'field_type' => 'boolean'],
            ['key' => 'level', 'field_type' => 'select', 'options' => ['A', 'B']],
        ])->map(fn (array $data, int $index) => $this->field($club, 'team', [
            ...$data, 'sort_order' => $index,
        ]));
        $category = ClubCategory::query()->create([
            'club_id' => $club->id, 'scope' => 'team', 'name' => 'Leistung',
            'color' => '#123ABC', 'is_active' => true, 'sort_order' => 0,
        ]);
        Sanctum::actingAs($owner);

        $values = [
            $definitions[0]->id => ' Erste ',
            $definitions[1]->id => 'Langer Wert',
            $definitions[2]->id => '12.50',
            $definitions[3]->id => '2026-09-24',
            $definitions[4]->id => false,
            $definitions[5]->id => 'B',
        ];
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}", [
            'values' => $values,
            'category_ids' => [$category->id],
        ])->assertOk()
            ->assertJsonPath('data.subject_type', 'team')
            ->assertJsonPath('data.fields.0.value', 'Erste')
            ->assertJsonPath('data.fields.2.value', '12.50')
            ->assertJsonPath('data.fields.4.value', false)
            ->assertJsonPath('data.categories.0.selected', true);

        $this->getJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}")
            ->assertOk()->assertJsonCount(6, 'data.fields')->assertJsonCount(1, 'data.categories');
        $this->assertDatabaseCount('club_custom_field_values', 6);
        $this->assertDatabaseHas('club_category_assignments', [
            'club_id' => $club->id, 'club_category_id' => $category->id,
            'subject_type' => 'team', 'subject_id' => $team->id,
        ]);

        $audit = Activity::query()->where('type', 'club.metadata_subject.updated')->firstOrFail();
        $this->assertSame(['entity_type' => 'team'], $audit->data);
        $encoded = $audit->data ? json_encode($audit->data) : '';
        $this->assertStringNotContainsString('Erste', $encoded);
        $this->assertStringNotContainsString('Leistung', $encoded);
    }

    public function test_validation_is_atomic_and_rejects_wrong_types_required_fields_and_foreign_configuration(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $required = $this->field($club, 'team', [
            'key' => 'required_text', 'field_type' => 'text', 'is_required' => true,
        ]);
        $date = $this->field($club, 'team', ['key' => 'date', 'field_type' => 'date']);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignField = $this->field($foreignClub, 'team', ['key' => 'foreign']);
        $foreignCategory = ClubCategory::query()->create([
            'club_id' => $foreignClub->id, 'scope' => 'team', 'name' => 'Fremd',
            'is_active' => true, 'sort_order' => 0,
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}", [
            'values' => [$date->id => '2026-02-30'], 'category_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('values.'.$date->id);
        $this->assertDatabaseCount('club_custom_field_values', 0);

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}", [
            'values' => [$required->id => 'ok', $foreignField->id => 'no'], 'category_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('values');
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}", [
            'values' => [$required->id => 'ok'], 'category_ids' => [$foreignCategory->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('category_ids');
        $this->assertDatabaseCount('club_custom_field_values', 0);
        $this->assertDatabaseCount('club_category_assignments', 0);
    }

    public function test_internal_external_member_event_inventory_and_team_subjects_are_club_scoped(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $regularMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([$member->id, $regularMember->id], [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'Extern',
            'email' => 'extern@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $event = Event::query()->create([
            'club_id' => $club->id, 'title' => 'Termin', 'type' => 'meeting',
            'visibility' => 'organization', 'start_time' => now()->addDay(),
        ]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id, 'name' => 'Ball', 'quantity_total' => 1,
            'quantity_available' => 1, 'condition' => 'good', 'status' => 'active',
            'requires_approval' => false,
        ]);
        $definitions = [];
        foreach (['member', 'team', 'event', 'inventory_item'] as $entityType) {
            $definitions[$entityType] = $this->field($club, $entityType, ['key' => 'note_'.$entityType]);
        }
        Sanctum::actingAs($owner);

        foreach ([
            ['member', $member->id],
            ['external_member', $external->id],
            ['team', $team->id],
            ['event', $event->id],
            ['inventory_item', $item->id],
        ] as [$subjectType, $subjectId]) {
            $this->getJson("/api/v1/clubs/{$club->id}/metadata/subjects/{$subjectType}/{$subjectId}")
                ->assertOk()->assertJsonPath('data.subject_type', $subjectType);
        }

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/member/{$member->id}", [
            'values' => [$definitions['member']->id => 'wird entfernt'], 'category_ids' => [],
        ])->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
            'reason' => 'Mitgliedschaft beendet',
        ])->assertOk();
        $this->assertDatabaseMissing('club_custom_field_values', [
            'club_id' => $club->id, 'subject_type' => 'member', 'subject_id' => $member->id,
        ]);

        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignTeam = Team::factory()->create(['club_id' => $otherClub->id]);
        $this->getJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$foreignTeam->id}")
            ->assertNotFound();

        Sanctum::actingAs($regularMember);
        $this->getJson("/api/v1/clubs/{$club->id}/metadata/subjects/member/{$regularMember->id}")
            ->assertForbidden();
    }

    public function test_inactive_history_is_preserved_and_used_configuration_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $field = $this->field($club, 'team', ['key' => 'history']);
        $category = ClubCategory::query()->create([
            'club_id' => $club->id, 'scope' => 'team', 'name' => 'Historie',
            'is_active' => true, 'sort_order' => 0,
        ]);
        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}", [
            'values' => [$field->id => 'bleibt'], 'category_ids' => [$category->id],
        ])->assertOk();

        $field->update(['is_active' => false]);
        $category->update(['is_active' => false]);
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/subjects/team/{$team->id}", [
            'values' => [], 'category_ids' => [],
        ])->assertOk()
            ->assertJsonPath('data.fields.0.value', 'bleibt')
            ->assertJsonPath('data.fields.0.is_active', false)
            ->assertJsonPath('data.categories.0.selected', true)
            ->assertJsonPath('data.categories.0.is_active', false);

        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/custom-fields/{$field->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('custom_field');
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->putJson("/api/v1/clubs/{$club->id}/metadata/custom-fields/{$field->id}", [
            'entity_type' => 'team', 'key' => 'history', 'label' => 'Historie',
            'field_type' => 'number', 'is_required' => false, 'is_sensitive' => true,
            'is_active' => false, 'sort_order' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('field_type');
        $this->putJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category->id}", [
            'scope' => 'member', 'name' => 'Historie', 'color' => null,
            'is_active' => false, 'sort_order' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('scope');

        $team->delete();
        $this->assertDatabaseMissing('club_custom_field_values', ['subject_type' => 'team', 'subject_id' => $team->id]);
        $this->assertDatabaseMissing('club_category_assignments', ['subject_type' => 'team', 'subject_id' => $team->id]);
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/custom-fields/{$field->id}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/metadata/categories/{$category->id}")->assertOk();
    }

    private function field(Club $club, string $entityType, array $overrides = []): ClubCustomFieldDefinition
    {
        return ClubCustomFieldDefinition::query()->create([
            'club_id' => $club->id,
            'entity_type' => $entityType,
            'key' => 'field',
            'label' => 'Internes Feld',
            'field_type' => 'text',
            'options' => null,
            'is_required' => false,
            'is_sensitive' => true,
            'is_active' => true,
            'sort_order' => 0,
            ...$overrides,
        ]);
    }
}
