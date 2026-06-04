<?php

namespace Tests\Feature;

use App\Models\ProfileRecommendation;
use App\Models\SkillEndorsement;
use App\Models\Sport;
use App\Models\SportSkill;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSportCvProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_exposes_linkedin_style_sport_cv(): void
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create([
            'profile_visibility' => 'public',
            'bio' => 'Ambitionierter Ausdauersportler.',
        ]);
        $coach = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Laufen',
            'slug' => 'running',
            'category' => 'endurance',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $skill = SportSkill::query()->create([
            'sport_id' => $sport->id,
            'key' => 'pacing',
            'name' => 'Pacing',
            'sort_order' => 1,
        ]);

        UserSport::query()->create([
            'user_id' => $athlete->id,
            'sport_id' => $sport->id,
            'status' => 'active',
            'experience_level' => 'advanced',
            'visibility' => 'public',
            'performance_metrics' => [
                'best_10k_time' => '39:20',
                'bodyweight_kg' => 72,
            ],
            'performance_visibility' => [
                'best_10k_time' => 'public',
                'bodyweight_kg' => 'private',
            ],
        ]);

        $userSkill = UserSportSkill::query()->create([
            'user_id' => $athlete->id,
            'sport_id' => $sport->id,
            'sport_skill_id' => $skill->id,
            'self_level' => 4,
            'is_visible' => true,
            'notes' => 'Konstante Splits.',
        ]);

        SkillEndorsement::query()->create([
            'user_sport_skill_id' => $userSkill->id,
            'endorser_id' => $coach->id,
            'relationship' => 'trainer',
            'level' => 4,
            'comment' => 'Sehr kontrolliert.',
        ]);

        ProfileRecommendation::query()->create([
            'profile_user_id' => $athlete->id,
            'author_id' => $coach->id,
            'relationship' => 'trainer',
            'body' => 'Zuverlässig und fokussiert.',
            'status' => 'approved',
        ]);

        $this->actingAs($viewer)
            ->get(route('auth.users.show', $athlete))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Users/Profile')
                ->where('profileUser.sport_cv.headline', 'Laufen')
                ->where('profileUser.sport_cv.best_metrics.0.key', 'best_10k_time')
                ->where('profileUser.sport_cv.best_metrics.0.value', '39:20')
                ->where('profileUser.sport_cv.top_skills.0.name', 'Pacing')
                ->where('profileUser.sport_cv.top_skills.0.endorsements_count', 1)
                ->where('profileUser.sport_cv.proof.0.value', 1)
                ->where('profileUser.sport_cv.profile_quality.version', '2026-06-03')
                ->where('profileUser.sport_cv.profile_quality.score', 80)
                ->where('profileUser.sport_cv.profile_quality.level', 'strong')
                ->where('profileUser.sport_cv.profile_quality.missing.0', 'skills')
                ->where('profileUser.sport_cv.career_timeline.0.type', 'sport_profile')
                ->where('profileUser.sport_cv.career_timeline.1.type', 'best_metric')
                ->where('profileUser.sport_cv.scout_card.ready', true)
                ->where('profileUser.sport_cv.scout_card.signals.recommendations', 1)
                ->where('profileUser.sport_cv.next_actions.0.key', 'skills')
                ->where('profileUser.sport_cv.privacy_matrix.version', '2026-06-03')
                ->where('profileUser.profile_privacy.version', '2026-06-03')
                ->where('profileUser.profile_privacy.summary.public_metrics', 1)
                ->where('profileUser.profile_privacy.summary.private_metrics', 1)
                ->where('profileUser.profile_privacy.sections.5.key', 'best_metrics')
                ->where('profileUser.profile_privacy.sections.5.visibility', 'field_level')
                ->missing('profileUser.sport_cv.best_metrics.1')
            );
    }

    public function test_private_profile_exposes_privacy_matrix_without_sport_cv_details(): void
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create([
            'profile_visibility' => 'private',
            'direct_message_privacy' => 'friends',
        ]);

        $this->actingAs($viewer)
            ->get(route('auth.users.show', $athlete))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Users/Profile')
                ->where('profileUser.sport_cv.headline', 'Privates Sportprofil')
                ->where('profileUser.profile_privacy.profile_visible_to_viewer', false)
                ->where('profileUser.profile_privacy.sections.0.visible_to_viewer', false)
                ->where('profileUser.profile_privacy.sections.5.visibility', 'private')
                ->where('profileUser.sport_cv.best_metrics', [])
            );
    }
}
