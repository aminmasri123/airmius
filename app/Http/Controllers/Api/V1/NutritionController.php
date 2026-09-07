<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ManagesNutritionPayloads;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NutritionGoalResource;
use App\Http\Resources\Api\V1\NutritionMealResource;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Services\Ai\AirmiusAiService;
use App\Services\NutritionFoodLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NutritionController extends Controller
{
    use ManagesNutritionPayloads;

    public function index(Request $request, AirmiusAiService $ai)
    {
        $request->validate(['date' => ['nullable', 'date']]);
        $user = $request->user();
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $goal = NutritionGoal::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'goal_type' => 'maintain',
                'diet_style' => 'balanced',
                'daily_calories_target' => 2200,
                'protein_target_g' => 120,
                'carbs_target_g' => 260,
                'fat_target_g' => 75,
                'water_target_ml' => 2500,
                'water_target_mode' => 'auto',
            ],
        );
        $meals = NutritionMeal::query()
            ->forUser($user)
            ->whereDate('eaten_on', $date)
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => [
                'selected_date' => $date,
                'goal' => (new NutritionGoalResource($goal))->resolve(),
                'meals' => NutritionMealResource::collection($meals)->resolve(),
                'summary' => $this->summaryForDate($user, $date),
                'weekly_summaries' => $this->weeklySummaries($user, $date),
                'water_recommendation' => $this->waterRecommendation($user, $goal, $date),
                'catalog' => $this->nutritionCatalog(),
                'recipes' => $this->nutritionRecipes($goal->goal_type, $goal->diet_style),
                'tips' => $this->nutritionTips($goal->goal_type),
                'ai_capabilities' => $ai->capabilities($user),
            ],
        ]);
    }

    public function searchFoods(Request $request, NutritionFoodLookupService $lookup)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        return response()->json([
            'data' => $lookup->search($data['q']),
        ]);
    }

    public function lookupBarcode(Request $request, NutritionFoodLookupService $lookup)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'min:6', 'max:32', 'regex:/^[0-9\\s\\-]+$/'],
        ]);

        $product = $lookup->barcode($data['barcode']);

        if (! $product) {
            return response()->json(['message' => __('nutrition.responses.product_not_found')], 404);
        }

        return response()->json(['data' => $product]);
    }

    public function analyzeMealImage(Request $request, AirmiusAiService $ai)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('airmius_ai.privacy.max_image_kb', 5120)],
            'ai_consent' => ['accepted'],
            'meal_type' => ['nullable', 'string', 'max:40'],
            'diet_style' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $suggestion = $ai->analyzeNutritionImage($request->user(), $data['image'], [
                'meal_type' => $data['meal_type'] ?? null,
                'diet_style' => $data['diet_style'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('Nutrition image analysis failed.', [
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => __('nutrition.responses.ai_unavailable'),
                'code' => 'nutrition_ai_unavailable',
            ], 422);
        }

        return response()->json([
            'data' => $suggestion,
            'message' => __('nutrition.responses.ai_suggestion_created'),
        ]);
    }

    public function updateGoal(Request $request)
    {
        $goal = NutritionGoal::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $this->validateGoalData($request),
        );

        return response()->json([
            'data' => (new NutritionGoalResource($goal))->resolve(),
            'message' => __('nutrition.responses.goal_saved'),
        ]);
    }

    public function storeMeal(Request $request)
    {
        $meal = NutritionMeal::query()->create(
            $this->mealPayload($request->user(), $this->validateMealData($request))
        );

        return (new NutritionMealResource($meal))
            ->additional(['message' => __('nutrition.responses.meal_saved')])
            ->response()
            ->setStatusCode(201);
    }

    public function storeWater(Request $request)
    {
        $entry = NutritionMeal::query()->create(
            $this->waterPayload($request->user(), $this->validateWaterData($request))
        );
        $waterTotalMl = NutritionMeal::query()
            ->forUser($request->user())
            ->whereDate('eaten_on', $entry->eaten_on)
            ->sum('water_ml');

        return response()->json([
            'data' => [
                ...(new NutritionMealResource($entry))->resolve(),
                'water_total_ml' => (int) $waterTotalMl,
            ],
            'message' => __('nutrition.responses.water_saved'),
        ], 201);
    }

    public function updateMeal(Request $request, NutritionMeal $nutritionMeal)
    {
        abort_unless((int) $nutritionMeal->user_id === (int) $request->user()->id, 403);

        $nutritionMeal->update(
            $this->mealPayload($request->user(), $this->validateMealData($request, true), $nutritionMeal)
        );

        return new NutritionMealResource($nutritionMeal->fresh());
    }

    public function destroyMeal(Request $request, NutritionMeal $nutritionMeal)
    {
        abort_unless((int) $nutritionMeal->user_id === (int) $request->user()->id, 403);

        $nutritionMeal->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
