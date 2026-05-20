<?php

namespace Tests\Feature;

use App\Models\NutritionMeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NutritionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_nutrition_dashboard_and_manage_day(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.nutrition.index', ['date' => '2026-05-19']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Nutrition/Index')
                ->where('selectedDate', '2026-05-19')
                ->where('goal.goal_type', 'maintain')
                ->where('todaySummary.calories', 0)
                ->has('catalog.meal_types')
                ->has('recipes')
            );

        $this->actingAs($user)
            ->patch(route('auth.nutrition.goal.update'), [
                'goal_type' => 'build_muscle',
                'daily_calories_target' => 2800,
                'protein_target_g' => 170,
                'carbs_target_g' => 330,
                'fat_target_g' => 90,
                'water_target_ml' => 3000,
                'diet_style' => 'high_protein',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('nutrition_goals', [
            'user_id' => $user->id,
            'goal_type' => 'build_muscle',
            'daily_calories_target' => 2800,
        ]);

        $this->actingAs($user)
            ->post(route('auth.nutrition.meals.store'), [
                'eaten_on' => '2026-05-19',
                'meal_type' => 'lunch',
                'title' => 'Reis mit Haehnchen',
                'calories' => 720,
                'protein_g' => 48,
                'carbs_g' => 86,
                'fat_g' => 18,
                'water_ml' => 500,
                'source' => 'manual',
                'items' => [
                    ['name' => 'Reis', 'amount' => '120 g'],
                    ['name' => 'Haehnchen', 'amount' => '180 g'],
                ],
            ])
            ->assertRedirect();

        $meal = NutritionMeal::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('auth.nutrition.index', ['date' => '2026-05-19']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('todaySummary.calories', 720)
                ->where('todaySummary.protein_g', 48)
                ->where('meals.0.title', 'Reis mit Haehnchen')
            );

        $this->actingAs($user)
            ->delete(route('auth.nutrition.meals.destroy', $meal))
            ->assertRedirect();

        $this->assertSoftDeleted('nutrition_meals', ['id' => $meal->id]);
    }

    public function test_mobile_nutrition_api_contract_can_log_and_summarize_meals(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/nutrition/goal', [
            'goal_type' => 'performance',
            'daily_calories_target' => 2600,
            'protein_target_g' => 140,
            'carbs_target_g' => 340,
            'fat_target_g' => 70,
            'water_target_ml' => 2800,
            'diet_style' => 'balanced',
        ])
            ->assertOk()
            ->assertJsonPath('data.goal_type', 'performance');

        $this->postJson('/api/v1/nutrition/meals', [
            'eaten_on' => '2026-05-19',
            'meal_type' => 'breakfast',
            'title' => 'Runner Oats',
            'calories' => 520,
            'protein_g' => 25,
            'carbs_g' => 72,
            'fat_g' => 12,
            'source' => 'recipe',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Runner Oats');

        $this->getJson('/api/v1/nutrition?date=2026-05-19')
            ->assertOk()
            ->assertJsonPath('data.goal.goal_type', 'performance')
            ->assertJsonPath('data.summary.calories', 520)
            ->assertJsonPath('data.meals.0.title', 'Runner Oats')
            ->assertJsonPath('data.catalog.meal_types.0.key', 'breakfast');
    }

    public function test_nutrition_food_lookup_uses_open_food_facts_without_api_key(): void
    {
        Http::fake([
            'https://world.openfoodfacts.org/cgi/search.pl*' => Http::response([
                'products' => [
                    [
                        'code' => '1234567890123',
                        'product_name' => 'Protein Skyr',
                        'brands' => 'Airmius Test',
                        'serving_size' => '150 g',
                        'nutriscore_grade' => 'b',
                        'nutriments' => [
                            'energy-kcal_serving' => 140,
                            'proteins_serving' => 18,
                            'carbohydrates_serving' => 9,
                            'fat_serving' => 2,
                            'fiber_serving' => 1,
                            'sugars_serving' => 7,
                        ],
                    ],
                ],
            ]),
            'https://world.openfoodfacts.org/api/v2/product/1234567890123.json*' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '1234567890123',
                    'product_name' => 'Protein Skyr',
                    'brands' => 'Airmius Test',
                    'serving_size' => '150 g',
                    'nutriments' => [
                        'energy-kcal_serving' => 140,
                        'proteins_serving' => 18,
                        'carbohydrates_serving' => 9,
                        'fat_serving' => 2,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('auth.nutrition.foods.search', ['q' => 'skyr']))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Protein Skyr')
            ->assertJsonPath('data.0.calories', 140)
            ->assertJsonPath('data.0.protein_g', 18);

        $this->actingAs($user)
            ->getJson(route('auth.nutrition.foods.barcode', ['barcode' => '1234567890123']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Protein Skyr')
            ->assertJsonPath('data.quantity_label', '150 g');
    }
}
