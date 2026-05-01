<?php

namespace App\Http\Controllers;

use App\Models\ProfileRecommendation;
use App\Models\SkillEndorsement;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportSkill;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileGamificationController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    public function storeSport(Request $request)
    {
        $data = $request->validate([
            'sport_id' => ['required', 'exists:sports,id'],
            'status' => ['required', Rule::in(['active', 'wants_to_learn', 'coach', 'interested'])],
            'experience_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'expert'])],
        ]);

        $user = $request->user();

        $userSport = UserSport::updateOrCreate(
            [
                'user_id' => $user->id,
                'sport_id' => $data['sport_id'],
            ],
            [
                'status' => $data['status'],
                'experience_level' => $data['experience_level'],
                'visibility' => 'public',
            ],
        );

        $createdSportProfile = $userSport->wasRecentlyCreated;

        Sport::query()
            ->with('skills')
            ->findOrFail($data['sport_id'])
            ->skills
            ->each(fn ($skill) => UserSportSkill::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'sport_skill_id' => $skill->id,
                ],
                [
                    'sport_id' => $skill->sport_id,
                    'self_level' => $data['status'] === 'wants_to_learn' ? 'learning' : 'developing',
                    'is_visible' => true,
                ],
            ));

        if ($createdSportProfile) {
            $this->gamification->grant($user, 'sport_profile_added', $userSport, [
                'sport_id' => $data['sport_id'],
                'status' => $data['status'],
            ]);
        }

        return back()->with('message', 'Sportprofil wurde aktualisiert.');
    }

    public function updateSkill(Request $request, UserSportSkill $userSportSkill)
    {
        abort_unless($request->user()->is($userSportSkill->user), 403);

        $data = $request->validate([
            'self_level' => ['required', Rule::in(['learning', 'developing', 'solid', 'strong', 'expert'])],
            'is_visible' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldLevel = $userSportSkill->self_level;

        $userSportSkill->update([
            'self_level' => $data['self_level'],
            'is_visible' => $data['is_visible'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);

        $reason = $this->skillLevelRank($data['self_level']) > $this->skillLevelRank($oldLevel)
            ? 'skill_level_improved'
            : 'skill_profile_refined';

        $this->gamification->grant($request->user(), $reason, $userSportSkill, [
            'old_level' => $oldLevel,
            'new_level' => $data['self_level'],
        ]);

        return back()->with('message', 'Skill wurde aktualisiert.');
    }

    public function endorse(Request $request, User $user, UserSportSkill $userSportSkill)
    {
        abort_unless($userSportSkill->user_id === $user->id, 404);
        abort_if($request->user()->is($user), 403);

        $data = $request->validate([
            'relationship' => ['required', Rule::in(['visitor', 'friend', 'team_member', 'trainer', 'club_admin'])],
            'level' => ['required', Rule::in(['confirmed', 'good', 'strong', 'exceptional'])],
            'comment' => ['nullable', 'string', 'max:400'],
        ]);

        $endorsement = SkillEndorsement::updateOrCreate(
            [
                'user_sport_skill_id' => $userSportSkill->id,
                'endorser_id' => $request->user()->id,
            ],
            $data,
        );

        if ($endorsement->wasRecentlyCreated) {
            $this->gamification->grant($user, 'skill_endorsed', $endorsement, [
                'skill_id' => $userSportSkill->sport_skill_id,
                'endorser_id' => $request->user()->id,
                'relationship' => $data['relationship'],
            ]);
        }

        return back()->with('message', 'Skill wurde bestätigt.');
    }

    public function recommend(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 403);

        $data = $request->validate([
            'relationship' => ['required', Rule::in(['visitor', 'friend', 'team_member', 'trainer', 'club_admin'])],
            'body' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        ProfileRecommendation::create([
            'profile_user_id' => $user->id,
            'author_id' => $request->user()->id,
            'relationship' => $data['relationship'],
            'body' => $data['body'],
            'status' => 'pending',
        ]);

        return back()->with('message', 'Empfehlung wurde gesendet und wartet auf Freigabe.');
    }

    public function approveRecommendation(Request $request, ProfileRecommendation $profileRecommendation)
    {
        abort_unless($request->user()->id === $profileRecommendation->profile_user_id, 403);

        $profileRecommendation->update(['status' => 'approved']);

        $this->gamification->grant($profileRecommendation->profileUser, 'recommendation_approved', $profileRecommendation, [
            'author_id' => $profileRecommendation->author_id,
            'relationship' => $profileRecommendation->relationship,
        ]);

        return back()->with('message', 'Empfehlung wurde veröffentlicht.');
    }

    public function rejectRecommendation(Request $request, ProfileRecommendation $profileRecommendation)
    {
        abort_unless($request->user()->id === $profileRecommendation->profile_user_id, 403);

        $profileRecommendation->update(['status' => 'rejected']);

        $this->gamification->penalize($profileRecommendation->author, 'recommendation_rejected', $profileRecommendation, [
            'profile_user_id' => $profileRecommendation->profile_user_id,
        ]);

        return back()->with('message', 'Empfehlung wurde abgelehnt.');
    }

    private function skillLevelRank(string $level): int
    {
        return [
            'learning' => 1,
            'developing' => 2,
            'solid' => 3,
            'strong' => 4,
            'expert' => 5,
        ][$level] ?? 1;
    }
}
