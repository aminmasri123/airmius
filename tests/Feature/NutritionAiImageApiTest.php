<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AirmiusAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class NutritionAiImageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_image_analysis_requires_explicit_ai_consent(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/nutrition/ai/meal-image', [
            'image' => UploadedFile::fake()->image('meal.jpg', 640, 480),
            'ai_consent' => '0',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai_consent');
    }

    public function test_mobile_image_analysis_returns_an_editable_unconfirmed_suggestion(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(AirmiusAiService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('analyzeNutritionImage')
                ->once()
                ->withArgs(fn (User $actualUser, UploadedFile $image, array $context): bool => (
                    $actualUser->is($user)
                    && $image->getClientOriginalName() === 'meal.png'
                    && $context === [
                        'meal_type' => 'lunch',
                        'diet_style' => 'balanced',
                    ]
                ))
                ->andReturn([
                    'title' => 'Geschätzte Bowl',
                    'calories' => 610,
                    'protein_g' => 31,
                    'carbs_g' => 74,
                    'fat_g' => 18,
                    'fiber_g' => 11,
                    'sugar_g' => 8,
                    'items' => [
                        ['name' => 'Reis', 'amount' => '150 g'],
                    ],
                    'warnings' => ['Portionsgröße bitte prüfen.'],
                    'needs_user_confirmation' => true,
                ]);
        });

        $this->post('/api/v1/nutrition/ai/meal-image', [
            'image' => UploadedFile::fake()->image('meal.png', 640, 480),
            'ai_consent' => '1',
            'meal_type' => 'lunch',
            'diet_style' => 'balanced',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Geschätzte Bowl')
            ->assertJsonPath('data.calories', 610)
            ->assertJsonPath('data.fiber_g', 11)
            ->assertJsonPath('data.items.0.name', 'Reis')
            ->assertJsonPath('data.needs_user_confirmation', true)
            ->assertJsonPath(
                'message',
                'KI-Vorschlag erstellt. Bitte prüfen und erst danach speichern.',
            );
    }
}
