<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLessonProgress;
use App\Models\MarketplaceProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LearningDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_previews_and_free_enrollment_preserve_access_rules(): void
    {
        $course = $this->course();
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $preview = $section->lessons()->create(['learning_course_id' => $course->id, 'title' => 'Preview', 'position' => 1, 'is_preview' => true, 'video_url' => 'https://example.test/preview.mp4', 'content' => 'Free preview']);
        $locked = $section->lessons()->create(['learning_course_id' => $course->id, 'title' => 'Protected', 'position' => 2, 'video_url' => 'https://example.test/private.mp4', 'content' => 'Private content']);
        $student = User::factory()->create();
        Sanctum::actingAs($student);
        $this->getJson('/api/v1/learning?language=en&price=free&level=beginner&q=Coach')->assertOk()->assertJsonCount(1, 'data.catalog');
        $this->getJson('/api/v1/learning?price=paid')->assertOk()->assertJsonCount(0, 'data.catalog');
        $detail = '/api/v1/learning/courses/'.$course->id;
        $this->getJson($detail)->assertOk()->assertJsonPath('data.sections.0.lessons.0.video_url', $preview->video_url)->assertJsonPath('data.sections.0.lessons.1.content', null)->assertJsonPath('data.sections.0.lessons.1.video_url', null)->assertJsonPath('data.can_use_learning_room', false);
        $this->postJson($detail.'/enroll')->assertSuccessful();
        $this->postJson($detail.'/enroll')->assertSuccessful();
        $this->assertSame(1, LearningEnrollment::where('user_id', $student->id)->count());
        $this->getJson($detail)->assertOk()->assertJsonPath('data.sections.0.lessons.1.content', 'Private content');
        $this->get(route('guest.e-learning', ['language' => 'en', 'q' => 'Coach']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('learningCourses.data', 1));
        $this->get(route('guest.e-learning', ['q' => 'does-not-exist']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('learningCourses.data', 0));
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($detail)->assertOk()->assertJsonPath('data.can_use_learning_room', false)->assertJsonPath('data.sections.0.lessons.1.video_url', null);
    }

    public function test_continue_learning_skips_completed_and_locked_lessons_and_is_user_scoped(): void
    {
        $course = $this->course();
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lessons = [];
        foreach ([0, 30, 0] as $i => $days) {
            $lessons[] = $section->lessons()->create(['learning_course_id' => $course->id, 'title' => 'Lesson '.$i, 'position' => $i, 'unlock_after_days' => $days]);
        }
        $user = User::factory()->create();
        $enrollment = LearningEnrollment::create(['user_id' => $user->id, 'learning_course_id' => $course->id, 'status' => 'active', 'started_at' => now()]);
        LearningLessonProgress::create(['learning_enrollment_id' => $enrollment->id, 'learning_lesson_id' => $lessons[0]->id, 'user_id' => $user->id, 'completed' => true]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/learning')->assertOk()->assertJsonPath('data.enrollments.0.next_lesson.id', $lessons[2]->id);
        $this->actingAs($user)->get(route('auth.learning.my-courses.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('enrollments.0.next_lesson.id', $lessons[2]->id));
        $enrollment->update(['status' => 'refunded']);
        $this->getJson('/api/v1/learning?language=en')->assertOk()->assertJsonPath('data.catalog.0.is_enrolled', false)->assertJsonPath('data.enrollments.0.next_lesson', null);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/learning')->assertOk()->assertJsonCount(0, 'data.enrollments');
    }

    public function test_paid_course_checkout_grants_access_only_after_confirmation(): void
    {
        Notification::fake();
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        $course = $this->course(['is_free' => false, 'price_cents' => 2500]);
        $product = MarketplaceProduct::create(['user_id' => $course->user_id, 'learning_course_id' => $course->id, 'title' => 'Course access', 'category' => 'course', 'offer_type' => 'online_course', 'product_type' => 'digital', 'is_shippable' => false, 'manages_stock' => false, 'price_cents' => 2500, 'currency' => 'EUR', 'status' => 'published', 'moderation_status' => 'approved']);
        $user = User::factory()->create(['country' => 'DE']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/learning/courses/'.$course->id)->assertOk()->assertJsonPath('data.course.purchase_product_id', $product->id);
        $this->postJson('/api/v1/learning/courses/'.$course->id.'/enroll')->assertForbidden();
        $this->postJson('/api/v1/commerce/cart/items/'.$product->id, ['quantity' => 1])->assertCreated();
        $order = $this->postJson('/api/v1/commerce/cart/checkout', ['provider' => 'bank_transfer', 'accepted_terms' => true, 'shipping_country' => 'DE', 'shipping_postal_code' => '10115', 'shipping_city' => 'Berlin', 'shipping_street' => 'Sportweg', 'shipping_house_number' => '7'])->assertCreated()->assertJsonPath('data.status', 'awaiting_transfer')->json('data.id');
        $this->assertDatabaseMissing('learning_enrollments', ['user_id' => $user->id, 'learning_course_id' => $course->id]);
        $this->postJson('/api/v1/admin/commerce/orders/'.$order.'/mark-paid')->assertForbidden();
        Permission::findOrCreate('marketplace.manage', 'web');
        $admin = User::factory()->create();
        $admin->givePermissionTo('marketplace.manage');
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/commerce/orders/'.$order.'/mark-paid')->assertOk();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/learning/courses/'.$course->id)->assertOk()->assertJsonPath('data.can_use_learning_room', true);
        $this->getJson('/api/v1/learning')->assertOk()->assertJsonCount(1, 'data.enrollments');
    }

    private function course(array $data = []): LearningCourse
    {
        return LearningCourse::create([...['user_id' => User::factory()->create(['name' => 'Coach'])->id, 'title' => 'New course', 'category' => 'training', 'level' => 'beginner', 'language' => 'en', 'status' => 'published', 'is_public' => true, 'is_free' => true, 'published_at' => now()], ...$data]);
    }
}
