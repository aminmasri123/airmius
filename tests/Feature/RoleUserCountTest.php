<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleUserCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_user_counts_work_with_sanctum_as_the_current_guard(): void
    {
        $role = Role::create(['name' => 'count_test', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        User::factory()->create();

        config(['auth.defaults.guard' => 'sanctum']);

        $counted = Role::query()->withCount('users')->findOrFail($role->id);

        $this->assertSame(1, $counted->users_count);
        $this->assertTrue($counted->users()->whereKey($user->id)->exists());
        $this->assertSame(1, $counted->users()->count());
    }
}
