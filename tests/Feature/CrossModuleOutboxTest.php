<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\DomainOutboxEvent;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrossModuleOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_commerce_order_changes_publish_minimized_domain_events(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $order = CommerceOrder::query()->create([
            'user_id' => $user->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4990,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $created = DomainOutboxEvent::query()
            ->where('event_name', 'commerce.order.created.v1')
            ->firstOrFail();
        $this->assertSame(4990, $created->payload['amount_cents']);
        $this->assertArrayNotHasKey('guest_email', $created->payload);
        $this->assertArrayNotHasKey('payload', $created->payload);

        $order->update(['status' => 'paid']);

        $statusChanged = DomainOutboxEvent::query()
            ->where('event_name', 'commerce.order.status_changed.v1')
            ->firstOrFail();
        $this->assertSame('pending', $statusChanged->payload['changes']['status']['from']);
        $this->assertSame('paid', $statusChanged->payload['changes']['status']['to']);
    }

    public function test_learning_enrollment_events_exclude_learning_content_and_scores(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $user->id,
            'title' => 'Athletik Grundlagen',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'active',
            'progress_percent' => 0,
            'started_at' => now(),
        ]);

        $enrollment->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        $completed = DomainOutboxEvent::query()
            ->where('event_name', 'content_learning.enrollment.completed.v1')
            ->firstOrFail();
        $this->assertSame($course->id, $completed->payload['course_id']);
        $this->assertSame(100, $completed->payload['progress_percent']);
        $this->assertArrayNotHasKey('score', $completed->payload);
        $this->assertArrayNotHasKey('content', $completed->payload);
    }
}
