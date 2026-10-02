<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileLearningQualityReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_can_find_foreign_private_courses_and_page_results(): void
    {
        $owner = User::factory()->create();
        for ($i = 1; $i <= 26; $i++) {
            $this->course($owner, "Course {$i}");
        }
        Sanctum::actingAs($this->reviewer());

        $this->getJson('/api/v1/admin/learning/courses')
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('total', 26)
            ->assertJsonPath('data.0.tutor.name', $owner->name);
        $this->getJson('/api/v1/admin/learning/courses?page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Course 26');
        $this->getJson('/api/v1/admin/learning/courses?q=Course%2026')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/learning/courses?q=absent')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_all_web_quality_states_and_audit_fields_are_saved_without_changing_publication(): void
    {
        $course = $this->course(User::factory()->create());
        $reviewer = $this->reviewer();
        Sanctum::actingAs($reviewer);

        foreach (['pending', 'approved', 'changes_requested', 'rejected'] as $status) {
            $this->putJson("/api/v1/admin/learning/courses/{$course->id}/quality", [
                'quality_status' => $status,
                'quality_note' => 'Review feedback',
                'featured' => true,
            ])->assertOk()->assertJsonPath('data.quality_status', $status)
                ->assertJsonPath('data.reviewed_by', $reviewer->id);
            $course->refresh();
            $this->assertSame($status, $course->quality_status);
            $this->assertSame('Review feedback', $course->quality_note);
            $this->assertNotNull($course->featured_at);
            $this->assertNotNull($course->reviewed_at);
            $this->assertSame('draft', $course->status);
            $this->assertFalse($course->is_public);
        }

        $this->putJson("/api/v1/admin/learning/courses/{$course->id}/quality", [
            'quality_status' => 'approved', 'quality_note' => null, 'featured' => false,
        ])->assertOk()->assertJsonPath('data.quality_note', null)->assertJsonPath('data.featured_at', null);
    }

    public function test_web_validation_is_shared_and_invalid_payloads_do_not_mutate_the_course(): void
    {
        $course = $this->course(User::factory()->create());
        $reviewer = $this->reviewer();
        Sanctum::actingAs($reviewer);
        $payload = ['quality_status' => 'published', 'quality_note' => str_repeat('x', 5001), 'featured' => 'yes'];
        $this->putJson("/api/v1/admin/learning/courses/{$course->id}/quality", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['quality_status', 'quality_note', 'featured']);
        $this->actingAs($reviewer)->putJson("/admin/learning/courses/{$course->id}/quality", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['quality_status', 'quality_note', 'featured']);
        $this->putJson("/api/v1/admin/learning/courses/{$course->id}/quality", [])
            ->assertUnprocessable()->assertJsonValidationErrors('quality_status');
        $this->assertNull($course->fresh()->reviewed_by);
        $this->putJson('/api/v1/admin/learning/courses/99999/quality', ['quality_status' => 'approved'])
            ->assertNotFound();
    }

    public function test_course_ownership_does_not_grant_review_permission(): void
    {
        $owner = User::factory()->create();
        $course = $this->course($owner);
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/learning/courses')->assertForbidden();
        $this->putJson("/api/v1/admin/learning/courses/{$course->id}/quality", ['quality_status' => 'approved'])
            ->assertForbidden();
        $this->assertNull($course->fresh()->reviewed_by);
    }

    public function test_review_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/learning/courses')->assertUnauthorized();
        $this->putJson('/api/v1/admin/learning/courses/1/quality', ['quality_status' => 'approved'])
            ->assertUnauthorized();
    }

    public function test_unverified_reviewers_and_admins_without_two_factor_are_denied(): void
    {
        $reviewer = $this->reviewer();
        $reviewer->forceFill(['email_verified_at' => null])->save();
        Sanctum::actingAs($reviewer);
        $this->getJson('/api/v1/admin/learning/courses')->assertForbidden();
        $reviewer->forceFill(['email_verified_at' => now()])->save();
        $reviewer->assignRole(Role::findOrCreate('super_admin', 'web'));
        Sanctum::actingAs($reviewer->fresh());
        $this->getJson('/api/v1/admin/learning/courses')->assertForbidden()
            ->assertJsonPath('code', 'admin_two_factor_required');
    }

    private function reviewer(): User
    {
        $role = Role::findOrCreate('course_quality_reviewer', 'web');
        $role->givePermissionTo(Permission::findOrCreate('subscriptions.manage', 'web'));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function course(User $owner, string $title = 'Private course'): LearningCourse
    {
        return LearningCourse::create([
            'user_id' => $owner->id, 'title' => $title, 'slug' => LearningCourse::uniqueSlug($title),
            'category' => 'training', 'level' => 'beginner', 'language' => 'en',
            'status' => 'draft', 'is_public' => false, 'is_free' => true, 'price_cents' => 0,
        ]);
    }
}
