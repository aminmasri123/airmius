<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Notifications\TrainerApplicationStatusUpdated;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class TrainerReviewDeliveryTest extends TestCase
{
    use RefreshDatabase;
    public function test_decisions_return_updated_status_without_smtp_and_cannot_be_repeated(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        config(['queue.default'=>'sync']);
        Queue::fake();
        Mail::shouldReceive('mailer')->never();
        $admin=User::factory()->create(); $admin->givePermissionTo('system.manage');
        Sanctum::actingAs($admin);
        foreach (['approve'=>'approved','reject'=>'rejected'] as $action=>$status) {
            $user=User::factory()->create(); $user->assignRole('coach');
            $application=UserRoleApplication::create(['user_id'=>$user->id,'type'=>'trainer','status'=>'pending','role_activated'=>true]);
            $url='/api/v1/admin/trainer-applications/'.$application->id.'/'.$action;
            $this->putJson($url,['review_notes'=>'Geprüft'])->assertOk()->assertJsonPath('data.application.status',$status);
            $this->assertSame($status,$application->fresh()->status);
            $this->assertSame($action==='approve',$user->fresh()->hasRole('coach'));
            $this->assertDatabaseHas('notifications',['user_id'=>$user->id,'type'=>'role.application_status_updated']);
            $this->putJson($url,['review_notes'=>'Geprüft'])->assertConflict();
        }
        Queue::assertPushed(SendQueuedNotifications::class,2);
        Queue::assertPushed(SendQueuedNotifications::class,fn($job)=>$job->notification instanceof TrainerApplicationStatusUpdated && $job->connection==='database');
    }
}
