<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\TrainingExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingExerciseSuitabilityTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Sanctum::actingAs($user);

        return $user;
    }

    private function exercise(User $user, array $attributes = []): TrainingExercise
    {
        return TrainingExercise::create(['created_by' => $user->id, 'name' => 'Übung', 'difficulty' => 'all', 'is_active' => true, ...$attributes]);
    }

    public function test_new_exercises_default_to_all_sports_with_an_independent_category(): void
    {
        $this->signIn();
        $this->postJson('/api/v1/training/exercises', ['scope' => 'personal', 'name' => 'Liegestütze', 'difficulty' => 'all', 'category' => 'strength'])
            ->assertCreated()->assertJsonPath('data.sport_types', [])->assertJsonPath('data.sport_type', null)->assertJsonPath('data.category', 'strength');
    }

    public function test_multiple_sports_round_trip_between_web_and_native_and_can_be_cleared_in_multipart(): void
    {
        $this->signIn();
        Sport::create(['name' => 'Fußball', 'slug' => 'fussball', 'is_active' => true]);
        $id = $this->postJson('/api/v1/training/exercises', ['scope' => 'personal', 'name' => 'Kniebeuge', 'difficulty' => 'all', 'sport_types' => ['Fußball', 'Straßenlauf', 'Fußball'], 'category' => 'strength'])
            ->assertCreated()->assertJsonPath('data.sport_types', ['Fußball', 'Straßenlauf'])->json('data.id');
        $this->get(route('auth.training.exercises.edit', $id))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('exercise.sport_types', ['Fußball', 'Straßenlauf'])->where('exercise.category', 'strength')->where('sports.0.value', 'Fußball'));

        $this->post(route('auth.training.exercises.update', $id), ['_method' => 'put', 'name' => 'Kniebeuge geändert', 'difficulty' => 'all', 'content_empty_arrays' => ['sport_types'], 'category' => 'mobility'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->getJson("/api/v1/training/exercises/$id")->assertOk()->assertJsonPath('data.sport_types', [])->assertJsonPath('data.sport_type', null)->assertJsonPath('data.category', 'mobility');
        $this->getJson('/api/v1/training/exercises?sport_type=fussball&category=mobility')->assertOk()->assertJsonPath('data.0.id', $id);
    }

    public function test_filter_keeps_general_and_matching_exercises_and_respects_category_and_visibility(): void
    {
        $user = $this->signIn();
        Sport::create(['name' => 'Fußball', 'slug' => 'fussball', 'is_active' => true]);
        $general = $this->exercise($user, ['sport_types' => [], 'category' => 'strength']);
        $legacyGeneral = $this->exercise($user, ['sport_type' => null, 'category' => 'strength']);
        $specific = $this->exercise($user, ['sport_types' => ['Straßenlauf', 'Fußball'], 'category' => 'strength']);
        $legacy = $this->exercise($user, ['sport_type' => 'fussball', 'category' => 'strength']);
        $this->exercise($user, ['sport_types' => ['Schwimmen'], 'category' => 'strength']);
        $this->exercise($user, ['sport_types' => [], 'category' => 'warmup']);
        $this->exercise($user, ['sport_types' => [], 'category' => 'strength', 'is_active' => false]);
        $outsider = User::factory()->create();
        $foreign = $this->exercise($outsider, ['sport_types' => [], 'category' => 'strength']);
        $this->exercise($outsider, ['sport_types' => ['Fußball'], 'category' => 'strength']);

        $data = $this->getJson('/api/v1/training/exercises?sport_type=fussball&category=strength')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$general->id, $legacyGeneral->id, $specific->id, $legacy->id], array_column($data, 'id'));
        $this->getJson("/api/v1/training/exercises/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/training/exercises/{$foreign->id}", ['name' => 'Fremd', 'difficulty' => 'all', 'sport_types' => ['Fußball']])->assertForbidden();
        $this->get(route('auth.training.exercises.index', ['sport_type' => 'fussball', 'category' => 'strength']))->assertInertia(fn (Assert $page) => $page
            ->has('exercises', 4)->where('filters.category', 'strength')->where('filters.sport_type', 'fussball'));
    }

    public function test_legacy_assignments_and_multi_selection_survive_edits_from_older_clients(): void
    {
        $user = $this->signIn();
        $legacy = $this->exercise($user, ['sport_type' => 'strength']);
        $this->getJson("/api/v1/training/exercises/{$legacy->id}")->assertOk()->assertJsonPath('data.sport_types', ['strength'])->assertJsonPath('data.category', null);
        $multi = $this->exercise($user, ['sport_type' => 'Fußball', 'sport_types' => ['Fußball', 'Straßenlauf'], 'category' => 'warmup']);
        $this->putJson("/api/v1/training/exercises/{$multi->id}", ['name' => 'Umbenannt', 'difficulty' => 'all', 'sport_type' => 'Fußball'])
            ->assertOk()->assertJsonPath('data.sport_types', ['Fußball', 'Straßenlauf'])->assertJsonPath('data.category', 'warmup');
        $this->putJson("/api/v1/training/exercises/{$multi->id}", ['name' => 'Geändert', 'difficulty' => 'all', 'sport_type' => 'Schwimmen'])
            ->assertOk()->assertJsonPath('data.sport_types', ['Schwimmen']);
    }

    public function test_invalid_metadata_is_rejected_without_overwriting_existing_selection(): void
    {
        $user = $this->signIn();
        $exercise = $this->exercise($user, ['sport_types' => ['Fußball'], 'category' => 'strength']);
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", ['name' => 'Übung', 'difficulty' => 'all', 'sport_types' => 'football', 'category' => 'football'])
            ->assertUnprocessable()->assertJsonValidationErrors(['sport_types', 'category']);
        $this->assertSame(['Fußball'], $exercise->fresh()->sport_types);
        $this->assertSame('strength', $exercise->fresh()->category);
    }
}
