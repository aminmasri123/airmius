<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ProfileRecommendation;
use App\Models\SkillEndorsement;
use App\Models\Sport;
use App\Models\SportSkill;
use App\Models\Team;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportProfileScoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sport_cv_api_exposes_verified_skills_recommendations_and_scout_card(): void
    {
        [$viewer, $athlete] = $this->seedScoutReadyAthlete();

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users/'.$athlete->id.'/sport-cv')
            ->assertOk()
            ->assertJsonPath('data.user_id', $athlete->id)
            ->assertJsonPath('data.profile.id', $athlete->id)
            ->assertJsonPath('data.profile.name', 'Amina Runner')
            ->assertJsonPath('data.profile.bio', '10k athlete')
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.headline', 'Laufen')
            ->assertJsonPath('data.best_metrics.0.key', 'best_10k_time')
            ->assertJsonPath('data.best_metrics.0.value', '39:20')
            ->assertJsonPath('data.verified_skills.0.name', 'Pacing')
            ->assertJsonPath('data.verified_skills.0.verification.status', 'verified')
            ->assertJsonPath('data.verified_skills.0.verification.source', 'trusted_endorsement')
            ->assertJsonPath('data.recommendation_summary.approved_count', 1)
            ->assertJsonPath('data.recommendation_summary.has_trainer_recommendation', true)
            ->assertJsonPath('data.scout_card.ready', true)
            ->assertJsonPath('data.scout_card.score', 85)
            ->assertJsonPath('data.scout_card.signals.verified_skills', 1);
    }

    public function test_scout_search_filters_public_profiles_by_sport_skill_and_score(): void
    {
        [$viewer, $athlete] = $this->seedScoutReadyAthlete();
        User::factory()->create([
            'name' => 'Private Talent',
            'profile_visibility' => 'private',
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/sport-profiles/scout-search?sport=running&skill=pacing&min_score=80')
            ->assertOk()
            ->assertJsonPath('data.version', '2026-06-03.linkedin_sport_profile.v1')
            ->assertJsonPath('data.results.0.user.id', $athlete->id)
            ->assertJsonPath('data.results.0.match.ready', true)
            ->assertJsonPath('data.results.0.match.reason_keys.0', 'scout_ready')
            ->assertJsonPath('data.results.0.sport_cv.verified_skills.0.verification.status', 'verified')
            ->assertJsonPath('data.facets.sports.running', 1)
            ->assertJsonPath('data.facets.skills.pacing', 1);
    }

    public function test_private_sport_cv_is_redacted_for_other_users(): void
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create([
            'profile_visibility' => 'private',
            'name' => 'Private Runner',
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users/'.$athlete->id.'/sport-cv')
            ->assertOk()
            ->assertJsonPath('data.user_id', $athlete->id)
            ->assertJsonPath('data.profile.id', $athlete->id)
            ->assertJsonPath('data.profile.name', 'Privates Profil')
            ->assertJsonPath('data.profile.bio', null)
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.headline_key', 'profile.sport_cv.private_headline')
            ->assertJsonPath('data.best_metrics', [])
            ->assertJsonPath('data.verified_skills', [])
            ->assertJsonPath('data.scout_card.ready', false);
    }

    public function test_sport_performance_sections_are_private_by_default_and_role_limited(): void
    {
        $athlete = User::factory()->create(['profile_visibility' => 'private']);
        $coach = User::factory()->create();
        $stranger = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $sport = Sport::query()->create([
            'name' => 'Laufen',
            'slug' => 'running',
            'category' => 'endurance',
            'is_active' => true,
        ]);

        $team->users()->attach($athlete->id, ['role' => 'Player']);
        $team->users()->attach($coach->id, ['role' => 'Coach']);

        UserSport::query()->create([
            'user_id' => $athlete->id,
            'sport_id' => $sport->id,
            'status' => 'active',
            'experience_level' => 'advanced',
            'visibility' => 'trainer',
            'sport_participation' => [
                ['label' => 'Ligaeinsatz', 'value' => '12 Starts', 'visibility' => 'trainer'],
                ['label' => 'Reha-Notiz', 'value' => 'Belastung intern steuern'],
            ],
            'development_goals' => [
                ['title' => '10 km unter 39:00', 'target' => '38:45', 'visibility' => 'trainer'],
            ],
            'sport_results' => [
                ['event' => 'Citylauf', 'result' => '39:20', 'visibility' => 'public'],
            ],
            'personal_bests' => [
                ['metric' => '10 km', 'value' => '39:20', 'visibility' => 'trainer'],
            ],
        ]);

        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/users/'.$athlete->id.'/sport-cv')
            ->assertOk()
            ->assertJsonPath('data.visibility', 'role_limited')
            ->assertJsonPath('data.performance_sections.participation.0.label', 'Ligaeinsatz')
            ->assertJsonPath('data.performance_sections.participation.0.sport.slug', 'running')
            ->assertJsonCount(1, 'data.performance_sections.participation')
            ->assertJsonPath('data.performance_sections.development_goals.0.title', '10 km unter 39:00')
            ->assertJsonPath('data.performance_sections.results.0.event', 'Citylauf')
            ->assertJsonPath('data.performance_sections.personal_bests.0.metric', '10 km')
            ->assertJsonPath('data.privacy_matrix.viewer_has_role_limited_access', true);

        Sanctum::actingAs($stranger);

        $this->getJson('/api/v1/users/'.$athlete->id.'/sport-cv')
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonMissingPath('data.performance_sections')
            ->assertJsonPath('data.best_metrics', []);
    }

    public function test_sport_performance_sections_do_not_cross_tenant_boundaries(): void
    {
        $athlete = User::factory()->create(['profile_visibility' => 'private']);
        $foreignCoach = User::factory()->create();
        $homeClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $homeTeam = Team::factory()->create(['club_id' => $homeClub->id]);
        $foreignTeam = Team::factory()->create(['club_id' => $foreignClub->id]);
        $sport = Sport::query()->create([
            'name' => 'Schwimmen',
            'slug' => 'swimming',
            'category' => 'endurance',
            'is_active' => true,
        ]);

        $homeTeam->users()->attach($athlete->id, ['role' => 'Player']);
        $foreignTeam->users()->attach($foreignCoach->id, ['role' => 'Coach']);

        UserSport::query()->create([
            'user_id' => $athlete->id,
            'sport_id' => $sport->id,
            'visibility' => 'trainer',
            'personal_bests' => [
                ['metric' => '100 m Freistil', 'value' => '1:02.4', 'visibility' => 'trainer'],
            ],
        ]);

        Sanctum::actingAs($foreignCoach);

        $this->getJson('/api/v1/users/'.$athlete->id.'/sport-cv')
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.privacy_matrix.viewer_has_role_limited_access', false)
            ->assertJsonMissingPath('data.performance_sections');
    }

    private function seedScoutReadyAthlete(): array
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create([
            'profile_visibility' => 'public',
            'name' => 'Amina Runner',
            'bio' => '10k athlete',
        ]);
        $coach = User::factory()->create(['name' => 'Coach Ada']);
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
            'body' => 'Zuverlaessig und fokussiert.',
            'status' => 'approved',
        ]);

        return [$viewer, $athlete];
    }
}
