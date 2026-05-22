<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesNutritionPayloads;
use App\Http\Resources\Api\V1\NutritionGoalResource;
use App\Http\Resources\Api\V1\NutritionMealResource;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\TrainingLog;
use App\Services\NutritionFoodLookupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NutritionController extends Controller
{
    use ManagesNutritionPayloads;

    public function index(Request $request)
    {
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

        $recentTraining = TrainingLog::query()
            ->where('user_id', $user->id)
            ->latest('performed_at')
            ->latest('id')
            ->limit(4)
            ->get(['id', 'title', 'sport_type', 'performed_at', 'duration_minutes', 'calories', 'intensity']);

        return Inertia::render('Auth/Dashboard/Nutrition/Index', [
            'selectedDate' => $date,
            'goal' => (new NutritionGoalResource($goal))->resolve(),
            'meals' => NutritionMealResource::collection($meals)->resolve(),
            'todaySummary' => $this->summaryForDate($user, $date),
            'weeklySummaries' => $this->weeklySummaries($user, $date),
            'waterRecommendation' => $this->waterRecommendation($user, $goal, $date),
            'catalog' => $this->nutritionCatalog(),
            'recipes' => $this->nutritionRecipes($goal->goal_type, $goal->diet_style),
            'tips' => $this->nutritionTips($goal->goal_type),
            'trainingSuggestions' => $this->trainingNutritionSuggestions($recentTraining, $goal->goal_type),
            'recentTraining' => $recentTraining->map(fn (TrainingLog $log) => [
                'id' => $log->id,
                'title' => $log->title,
                'sport_type' => $log->sport_type,
                'performed_at' => $log->performed_at?->toIso8601String(),
                'duration_minutes' => $log->duration_minutes,
                'calories' => $log->calories,
                'intensity' => $log->intensity,
            ])->values(),
        ]);
    }

    public function updateGoal(Request $request)
    {
        $goal = NutritionGoal::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $this->validateGoalData($request),
        );

        return back()->with('success', 'Ernaehrungsziel wurde gespeichert.');
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
            return response()->json([
                'message' => 'Kein Produkt fuer diesen Barcode gefunden.',
            ], 404);
        }

        return response()->json([
            'data' => $product,
        ]);
    }

    public function storeMeal(Request $request)
    {
        $meal = NutritionMeal::query()->create(
            $this->mealPayload($request->user(), $this->validateMealData($request))
        );

        return back()->with('success', 'Mahlzeit "'.$meal->title.'" wurde gespeichert.');
    }

    public function storeWater(Request $request)
    {
        $entry = NutritionMeal::query()->create(
            $this->waterPayload($request->user(), $this->validateWaterData($request))
        );

        return back()->with('success', $entry->water_ml.' ml wurden eingetragen.');
    }

    public function updateMeal(Request $request, NutritionMeal $nutritionMeal)
    {
        abort_unless((int) $nutritionMeal->user_id === (int) $request->user()->id, 403);

        $nutritionMeal->update(
            $this->mealPayload($request->user(), $this->validateMealData($request, true), $nutritionMeal)
        );

        return back()->with('success', 'Mahlzeit wurde aktualisiert.');
    }

    public function destroyMeal(Request $request, NutritionMeal $nutritionMeal)
    {
        abort_unless((int) $nutritionMeal->user_id === (int) $request->user()->id, 403);

        $nutritionMeal->delete();

        return back()->with('success', 'Mahlzeit wurde geloescht.');
    }
}
