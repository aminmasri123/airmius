<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ManagesNutritionPayloads;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NutritionGoalResource;
use App\Http\Resources\Api\V1\NutritionMealResource;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Services\NutritionFoodLookupService;
use Illuminate\Http\Request;

class NutritionController extends Controller
{
    use ManagesNutritionPayloads;

    public function index(Request $request)
    {
        $user = $request->user();
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $goal = NutritionGoal::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['goal_type' => 'maintain', 'diet_style' => 'balanced'],
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
                'catalog' => $this->nutritionCatalog(),
                'recipes' => $this->nutritionRecipes($goal->goal_type, $goal->diet_style),
                'tips' => $this->nutritionTips($goal->goal_type),
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
            return response()->json(['message' => 'Kein Produkt fuer diesen Barcode gefunden.'], 404);
        }

        return response()->json(['data' => $product]);
    }

    public function updateGoal(Request $request)
    {
        $goal = NutritionGoal::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $this->validateGoalData($request),
        );

        return response()->json([
            'data' => (new NutritionGoalResource($goal))->resolve(),
            'message' => 'Ernaehrungsziel gespeichert.',
        ]);
    }

    public function storeMeal(Request $request)
    {
        $meal = NutritionMeal::query()->create(
            $this->mealPayload($request->user(), $this->validateMealData($request))
        );

        return (new NutritionMealResource($meal))
            ->additional(['message' => 'Mahlzeit gespeichert.'])
            ->response()
            ->setStatusCode(201);
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
