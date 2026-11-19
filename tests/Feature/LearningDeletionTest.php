<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\MarketplaceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningDeletionTest extends TestCase
{
    use RefreshDatabase;

    public static function platforms(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('platforms')]
    public function test_course_deletion_requires_confirmation_and_cascades_only_the_selected_course(bool $api): void
    {
        [$course, $section, $lesson, $enrollment] = $this->fixture();
        $variant = LearningCourse::create(['user_id' => $course->user_id, 'title' => 'Translation', 'category' => 'training', 'level' => 'beginner', 'language' => 'en', 'translation_group' => $course->translation_group]);
        $product = MarketplaceProduct::create(['user_id' => $course->user_id, 'learning_course_id' => $course->id, 'title' => 'Course sale', 'status' => 'published', 'price_cents' => 1000]);
        $order = CommerceOrder::create(['user_id' => $enrollment->user_id, 'orderable_type' => MarketplaceProduct::class, 'orderable_id' => $product->id, 'type' => 'marketplace_product', 'provider' => 'bank_transfer', 'amount_cents' => 1000, 'currency' => 'EUR', 'status' => 'completed', 'completed_at' => now()]);
        $item = $order->items()->create(['orderable_type' => MarketplaceProduct::class, 'orderable_id' => $product->id, 'title' => $product->title, 'quantity' => 1, 'unit_gross_cents' => 1000, 'total_cents' => 1000, 'currency' => 'EUR']);
        DB::table('learning_certificates')->insert(['learning_course_id' => $course->id, 'learning_enrollment_id' => $enrollment->id, 'user_id' => $enrollment->user_id, 'code' => 'DEL-TEST', 'issued_at' => now()]);
        $this->login($course->tutor, $api);
        $url = $this->courseUrl($course, $api);
        $this->deleteJson($url)->assertUnprocessable();
        $this->deleteJson($url, ['confirmation' => 'delete'])->assertUnprocessable();
        $this->assertDatabaseHas('learning_courses', ['id' => $course->id]);
        $response = $api ? $this->deleteJson($url, ['confirmation' => 'DELETE']) : $this->delete($url, ['confirmation' => 'DELETE']);
        $api ? $response->assertOk() : $response->assertRedirect(route('auth.learning.studio.index'));
        foreach (['learning_courses' => $course->id, 'learning_course_sections' => $section->id, 'learning_lessons' => $lesson->id, 'learning_enrollments' => $enrollment->id] as $table => $id) {
            $this->assertDatabaseMissing($table, ['id' => $id]);
        }
        foreach (['learning_quizzes', 'learning_assignments', 'learning_certificates'] as $table) {
            $this->assertDatabaseMissing($table, ['learning_course_id' => $course->id]);
        }
        $this->assertDatabaseHas('learning_courses', ['id' => $variant->id]);
        $this->assertDatabaseHas('marketplace_products', ['id' => $product->id, 'learning_course_id' => null, 'status' => 'archived']);
        $this->assertDatabaseHas('commerce_orders', ['id' => $order->id, 'status' => 'completed', 'amount_cents' => 1000]);
        $this->assertDatabaseHas('commerce_order_items', ['id' => $item->id, 'total_cents' => 1000]);
        $this->assertDatabaseCount('learning_lesson_comments', 0);
        $this->assertDatabaseCount('learning_lesson_progress', 0);
    }

    #[DataProvider('platforms')]
    public function test_section_deletion_removes_linked_content_but_keeps_course_records(bool $api): void
    {
        [$course, $section, $lesson, $enrollment] = $this->fixture();
        $other = $course->sections()->create(['title' => 'Keep', 'position' => 5]);
        $remaining = $course->lessons()->create(['learning_course_section_id' => $other->id, 'title' => 'Keep lesson', 'duration_minutes' => 20]);
        $courseQuiz = $course->quizzes()->create(['title' => 'Course-wide test']);
        DB::table('learning_certificates')->insert(['learning_course_id' => $course->id, 'learning_enrollment_id' => $enrollment->id, 'user_id' => $enrollment->user_id, 'code' => 'KEEP-CERT', 'issued_at' => now()]);
        $this->login($course->tutor, $api);
        $url = $this->courseUrl($course, $api).'/sections/'.$section->id;
        $this->deleteJson($url)->assertUnprocessable();
        $response = $api ? $this->deleteJson($url, ['confirmation' => 'DELETE']) : $this->from(route('auth.learning.studio.index', ['course' => $course->id]))->delete($url, ['confirmation' => 'DELETE']);
        $api ? $response->assertOk() : $response->assertRedirect();
        $this->assertDatabaseMissing('learning_course_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('learning_lessons', ['id' => $lesson->id]);
        $this->assertDatabaseHas('learning_courses', ['id' => $course->id, 'estimated_minutes' => 20]);
        $this->assertDatabaseHas('learning_course_sections', ['id' => $other->id, 'position' => 1]);
        $this->assertDatabaseHas('learning_lessons', ['id' => $remaining->id]);
        $this->assertDatabaseHas('learning_quizzes', ['id' => $courseQuiz->id]);
        $this->assertDatabaseCount('learning_quizzes', 1);
        $this->assertDatabaseCount('learning_assignments', 0);
        $this->assertDatabaseCount('learning_lesson_comments', 0);
        $this->assertDatabaseCount('learning_lesson_progress', 0);
        $this->assertDatabaseHas('learning_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('learning_certificates', ['code' => 'KEEP-CERT']);
    }

    #[DataProvider('platforms')]
    public function test_students_and_other_trainers_cannot_delete_and_section_must_belong_to_course(bool $api): void
    {
        [$course, $section, , $enrollment] = $this->fixture();
        foreach ([User::factory()->create(), User::findOrFail($enrollment->user_id)] as $outsider) {
            $this->login($outsider, $api);
            $this->deleteJson($this->courseUrl($course, $api), ['confirmation' => 'DELETE'])->assertForbidden();
            $this->deleteJson($this->courseUrl($course, $api).'/sections/'.$section->id, ['confirmation' => 'DELETE'])->assertForbidden();
        }
        $ownOtherCourse = LearningCourse::create(['user_id' => $course->user_id, 'title' => 'Other', 'category' => 'training', 'level' => 'beginner']);
        $this->login($course->tutor, $api);
        $this->deleteJson($this->courseUrl($ownOtherCourse, $api).'/sections/'.$section->id, ['confirmation' => 'DELETE'])->assertNotFound();
        $this->assertDatabaseHas('learning_course_sections', ['id' => $section->id]);
        $this->assertDatabaseHas('learning_courses', ['id' => $course->id]);
    }

    public function test_guest_cannot_delete(): void
    {
        [$course, $section] = $this->fixture();
        $this->deleteJson($this->courseUrl($course, true), ['confirmation' => 'DELETE'])->assertUnauthorized();
        $this->deleteJson($this->courseUrl($course, true).'/sections/'.$section->id, ['confirmation' => 'DELETE'])->assertUnauthorized();
        $this->deleteJson($this->courseUrl($course, false), ['confirmation' => 'DELETE'])->assertUnauthorized();
    }

    private function login(User $user, bool $api): void
    {
        $api ? Sanctum::actingAs($user) : $this->actingAs($user);
    }

    private function courseUrl(LearningCourse $course, bool $api): string
    {
        return ($api ? '/api/v1/learning-studio/courses/' : '/learning/studio/courses/').$course->id;
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'Delete target', 'category' => 'training', 'level' => 'beginner']);
        $section = $course->sections()->create(['title' => 'Delete section', 'position' => 1]);
        $lesson = $course->lessons()->create(['learning_course_section_id' => $section->id, 'title' => 'Delete lesson', 'duration_minutes' => 10]);
        $course->quizzes()->create(['learning_lesson_id' => $lesson->id, 'title' => 'Linked quiz']);
        $course->assignments()->create(['learning_lesson_id' => $lesson->id, 'title' => 'Linked assignment']);
        $enrollment = LearningEnrollment::create(['learning_course_id' => $course->id, 'user_id' => $student->id, 'status' => 'active']);
        DB::table('learning_lesson_progress')->insert(['learning_enrollment_id' => $enrollment->id, 'learning_lesson_id' => $lesson->id, 'completed' => true]);
        DB::table('learning_lesson_comments')->insert(['learning_lesson_id' => $lesson->id, 'user_id' => $student->id, 'body' => 'Remove comment']);

        return [$course, $section, $lesson, $enrollment];
    }
}
