<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserSportSkill;
use App\Services\SportProfileScoutService;
use App\Services\Training\AthleteSportProfileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SportProfileController extends Controller
{
    public function index(Request $request, AthleteSportProfileService $profiles)
    {
        return response()->json([
            'data' => $profiles->settingsPayload($request->user()),
        ]);
    }

    public function me(Request $request, SportProfileScoutService $profiles)
    {
        return response()->json([
            'data' => $profiles->sportCv($request->user(), $request->user()),
        ]);
    }

    public function show(Request $request, User $user, SportProfileScoutService $profiles)
    {
        return response()->json([
            'data' => $profiles->sportCv($user, $request->user()),
        ]);
    }

    public function scoutSearch(Request $request, SportProfileScoutService $profiles)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', 'string', 'max:80'],
            'skill' => ['nullable', 'string', 'max:80'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        return response()->json([
            'data' => $profiles->scoutSearch($request->user(), $filters),
        ]);
    }

    public function update(
        Request $request,
        Sport $sport,
        AthleteSportProfileService $profiles,
    ) {
        abort_unless($sport->is_active, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'wants_to_learn', 'coach', 'interested'])],
            'experience_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'expert', 'elite'])],
            'visibility' => ['required', Rule::in(['private', 'trainer', 'public'])],
            'metrics' => ['nullable', 'array'],
            'metric_visibility' => ['nullable', 'array'],
            'metric_visibility.*' => ['nullable', Rule::in(['private', 'trainer', 'public'])],
            'unknown_metrics' => ['nullable', 'array'],
            'unknown_metrics.*' => ['nullable', 'boolean'],
        ]);

        $profile = $profiles->updateProfile($request->user(), $sport, $data);

        $sport->loadMissing('skills:id,sport_id');
        foreach ($sport->skills as $skill) {
            UserSportSkill::query()->firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'sport_skill_id' => $skill->id,
                ],
                [
                    'sport_id' => $sport->id,
                    'self_level' => $data['status'] === 'wants_to_learn' ? 'learning' : 'developing',
                    'is_visible' => true,
                ],
            );
        }

        return response()->json([
            'data' => collect($profiles->settingsPayload($request->user()))
                ->firstWhere('sport.id', $sport->id),
            'message' => $profile->wasRecentlyCreated
                ? 'Sportprofil wurde angelegt.'
                : 'Sportprofil wurde aktualisiert.',
        ]);
    }

    public function destroy(
        Request $request,
        Sport $sport,
        AthleteSportProfileService $profiles,
    ) {
        $deleted = $request->user()
            ->sportProfiles()
            ->where('sport_id', $sport->id)
            ->delete();

        if ($deleted) {
            $request->user()
                ->sportSkills()
                ->where('sport_id', $sport->id)
                ->delete();
        }

        return response()->json([
            'data' => [
                'deleted' => (bool) $deleted,
                'sport_id' => $sport->id,
                'profiles' => $profiles->settingsPayload($request->user()),
            ],
        ]);
    }

    public function updateSkill(Request $request, UserSportSkill $userSportSkill)
    {
        abort_unless((int) $userSportSkill->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'self_level' => ['required', Rule::in(['learning', 'developing', 'solid', 'strong', 'expert'])],
            'is_visible' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $userSportSkill->update($data);

        return response()->json([
            'data' => [
                'id' => $userSportSkill->id,
                'self_level' => $userSportSkill->self_level,
                'is_visible' => $userSportSkill->is_visible,
                'notes' => $userSportSkill->notes,
            ],
            'message' => 'Fähigkeit wurde aktualisiert.',
        ]);
    }
}
