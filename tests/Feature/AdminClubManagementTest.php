<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\File;
use App\Models\Post;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubDataErasureService;
use App\Services\ClubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminClubManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_see_clubs_outside_their_workspace(): void
    {
        $admin = $this->platformUser('admin');
        $foreignOwner = User::factory()->create();
        Club::factory()->create([
            'name' => 'Foreign Athletics Club',
            'owner_id' => $foreignOwner->id,
            'verification_status' => 'verified',
            'city' => 'Berlin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clubs.index', ['query' => 'Foreign Athletics']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/Clubs/Index')
                ->where('canDeleteClubs', false)
                ->has('clubs.data', 1)
                ->where('clubs.data.0.name', 'Foreign Athletics Club')
                ->where('clubs.data.0.owner.id', $foreignOwner->id)
                ->where('summary.total', 1)
            );
    }

    public function test_super_admin_can_delete_a_foreign_club_after_exact_name_confirmation(): void
    {
        $superAdmin = $this->platformUser('super_admin');
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Delete Me FC',
            'owner_id' => $foreignOwner->id,
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->delete(route('admin.clubs.destroy', $club), [
                'confirmation_name' => 'Delete Me FC',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('users', ['id' => $foreignOwner->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $foreignOwner->id,
            'type' => 'club.deleted_by_platform',
        ]);
    }

    public function test_club_deletion_requires_super_admin_and_exact_name(): void
    {
        $admin = $this->platformUser('admin');
        $superAdmin = $this->platformUser('super_admin');
        $club = Club::factory()->create([
            'name' => 'Protected Club',
            'owner_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.clubs.destroy', $club), [
                'confirmation_name' => 'Protected Club',
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->from(route('admin.clubs.index'))
            ->delete(route('admin.clubs.destroy', $club), [
                'confirmation_name' => 'Wrong name',
            ])
            ->assertRedirect(route('admin.clubs.index'))
            ->assertSessionHasErrors('confirmation_name');

        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    private function platformUser(string $roleName): User
    {
        $permission = Permission::findOrCreate('system.manage', 'web');
        $role = Role::findOrCreate($roleName, 'web');
        $role->givePermissionTo($permission);

        $user = User::factory()->create([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_cannot_delete_clubs_with_accounting_records(): void
    {
        $admin = $this->platformUser('super_admin');
        $owner = User::factory()->create();
        foreach ([
            'invoices' => ['number' => 'AUDIT', 'amount' => 25, 'due_date' => now()],
            'payments' => ['user_id' => $owner->id, 'amount' => 25, 'status' => 'paid'],
            'bank_transactions' => ['transaction_hash' => str_repeat('a', 64), 'amount' => 25],
            'club_finance_entries' => ['booked_on' => today(), 'type' => 'income',
                'category' => 'other', 'title' => 'Audit', 'amount' => 25, 'account' => 'cash'],
        ] as $table => $attributes) {
            $club = Club::factory()->create(['owner_id' => $owner->id]);
            $recordId = DB::table($table)->insertGetId(['club_id' => $club->id, ...$attributes]);
            $this->actingAs($admin)
                ->withSession(['auth.password_confirmed_at' => now()->timestamp])
                ->deleteJson(route('admin.clubs.destroy', $club), ['confirmation_name' => $club->name])
                ->assertUnprocessable()->assertJsonValidationErrors('club');
            $this->assertDatabaseHas('clubs', ['id' => $club->id]);
            $this->assertDatabaseHas($table, ['id' => $recordId, 'club_id' => $club->id]);
        }
    }

    public function test_direct_service_delete_cannot_bypass_accounting_protection(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        DB::table('invoices')->insert(['club_id' => $club->id, 'number' => 'AUDIT',
            'amount' => 25, 'due_date' => now()]);
        try {
            app(ClubService::class)->delete($club);
            $this->fail('Accounting records must block direct deletion.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    public function test_super_admin_cannot_delete_club_with_active_provider_subscription(): void
    {
        $admin = $this->platformUser('super_admin');
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $plan = SubscriptionPlan::create(['slug' => 'deletion-test', 'name' => 'Test']);
        ClubSubscription::updateOrCreate(['club_id' => $club->id], [
            'subscription_plan_id' => $plan->id, 'status' => 'active',
            'provider_subscription_id' => 'sub_deletion_test',
        ]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->deleteJson(route('admin.clubs.destroy', $club), ['confirmation_name' => $club->name])
            ->assertUnprocessable()->assertJsonValidationErrors('club');

        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('club_subscriptions', ['club_id' => $club->id,
            'provider_subscription_id' => 'sub_deletion_test']);
    }

    public function test_failed_admin_purge_rolls_back_owned_records_and_keeps_uploads(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);
        $admin = $this->platformUser('super_admin');
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $file = File::create(['club_id' => $club->id, 'user_id' => $club->owner_id,
            'path' => 'audit/rollback.pdf', 'type' => 'application/pdf', 'size' => 4]);
        Storage::disk('public')->put($file->path, 'test');
        $this->mock(ClubService::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->andThrow(new \RuntimeException('Injected failure'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
                ->deleteJson(route('admin.clubs.destroy', $club), ['confirmation_name' => $club->name]);
            $this->fail('The injected purge failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('files', ['id' => $file->id, 'club_id' => $club->id]);
        Storage::disk('public')->assertExists($file->path);
        $this->assertDatabaseCount('club_deletion_file_cleanups', 0);
    }

    public function test_super_admin_erases_owned_data_and_media_but_keeps_shared_uploads(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['filesystems.uploads_disk' => 'public']);
        $admin = $this->platformUser('super_admin');
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $other = Club::factory()->create(['owner_id' => $owner->id]);
        $file = File::create(['club_id' => $club->id, 'user_id' => $owner->id,
            'path' => 'audit/document.pdf', 'type' => 'application/pdf', 'size' => 4]);
        $shared = File::create(['club_id' => $club->id, 'user_id' => $owner->id,
            'path' => 'audit/shared.pdf', 'type' => 'application/pdf', 'size' => 4]);
        $otherFile = File::create(['club_id' => $other->id, 'user_id' => $owner->id,
            'path' => $shared->path, 'type' => 'application/pdf', 'size' => 4]);
        $post = Post::factory()->create(['club_id' => $club->id, 'user_id' => $owner->id,
            'image' => 'private-post-media/audit/image.jpg', 'visibility' => 'private']);
        Storage::disk('public')->put($file->path, 'test');
        Storage::disk('public')->put($shared->path, 'test');
        Storage::disk('local')->put($post->image, 'test');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->deleteJson(route('admin.clubs.destroy', $club), ['confirmation_name' => $club->name])
            ->assertOk();

        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertDatabaseMissing('files', ['id' => $shared->id]);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('files', ['id' => $otherFile->id]);
        $this->assertDatabaseHas('clubs', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        Storage::disk('public')->assertMissing($file->path);
        Storage::disk('public')->assertExists($shared->path);
        Storage::disk('local')->assertMissing($post->image);
    }

    public function test_failed_admin_upload_cleanup_is_retried_without_undoing_deletion(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);
        $admin = $this->platformUser('super_admin');
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $file = File::create(['club_id' => $club->id, 'user_id' => $club->owner_id,
            'path' => 'audit/retry.pdf', 'type' => 'application/pdf', 'size' => 4]);
        Storage::disk('public')->put($file->path, 'test');
        $this->partialMock(ClubDataErasureService::class, function ($mock) {
            $mock->shouldReceive('cleanupFiles')->once()->andThrow(new \RuntimeException('Storage unavailable'));
        });

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->deleteJson(route('admin.clubs.destroy', $club), ['confirmation_name' => $club->name])
            ->assertOk();
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseCount('club_deletion_file_cleanups', 1);
        Storage::disk('public')->assertExists($file->path);

        $this->app->forgetInstance(ClubDataErasureService::class);
        $this->artisan('airmius:process-club-deletions')->assertSuccessful();
        Storage::disk('public')->assertMissing($file->path);
        $this->assertDatabaseCount('club_deletion_file_cleanups', 0);
    }
}
