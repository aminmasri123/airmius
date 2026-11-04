<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Notifications\TrainerRegistrationReviewRequested;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class TrainerSubmissionDeliveryTest extends TestCase
{
    use RefreshDatabase;
    public function test_first_submission_succeeds_without_contacting_mail_and_retry_does_not_duplicate(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        config(['queue.default' => 'sync']);
        Queue::fake();
        Mail::shouldReceive('mailer')->never();
        $reviewer = User::factory()->create();
        $reviewer->givePermissionTo('system.manage');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = ['type'=>'trainer', 'application_data'=>['experience'=>'Neu im Gebiet']];
        $this->postJson('/api/v1/role-applications', $payload)->assertCreated();
        $this->assertTrue($user->fresh()->hasRole('coach'));
        Queue::assertPushed(SendQueuedNotifications::class, function ($job) use ($reviewer) {
            return $job->notification instanceof TrainerRegistrationReviewRequested
                && $job->connection === 'database'
                && $job->notifiables->contains('id', $reviewer->id);
        });
        $this->postJson('/api/v1/role-applications', $payload)->assertOk();
        $this->assertSame(1, UserRoleApplication::where('user_id', $user->id)->count());
        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }
}
