<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'first_name' => 'Test',
            'last_name' => 'Name',
            'email' => 'test@example.com',
            'athlete_license_number' => 'PROFILE-LIC-1',
            'athlete_license_valid_until' => '2027-06-30',
            'profile_visibility' => 'public',
            'bio' => 'Updated bio',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
        $this->assertEquals('Updated bio', $user->fresh()->bio);
        $this->assertSame('PROFILE-LIC-1', $user->fresh()->athlete_license_number);
        $this->assertSame('2027-06-30', $user->fresh()->athlete_license_valid_until?->toDateString());
    }
}
