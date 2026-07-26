<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClubImageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_owner_can_upload_a_cover_through_the_mobile_api(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post('/api/v1/clubs/'.$club->id.'/images', [
                'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 600),
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $club->id)
            ->assertJsonPath('data.can_manage', true);

        $path = $club->fresh()->cover_image;
        $this->assertNotNull($path);
        Storage::disk(UploadStorage::disk())->assertExists($path);
    }

    public function test_regular_member_cannot_replace_the_club_cover(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post('/api/v1/clubs/'.$club->id.'/images', [
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertForbidden();

        $this->assertNull($club->fresh()->cover_image);
    }
}
