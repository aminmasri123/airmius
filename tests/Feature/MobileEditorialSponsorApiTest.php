<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileEditorialSponsorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_review_publish_and_delete_with_revisions(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo($this->permissions([
            'blog.view',
            'blog.create',
            'blog.update',
            'blog.delete',
            'blog.publish',
            'blog.manage',
        ]));
        $category = BlogCategory::query()->where('is_active', true)->firstOrFail();

        Sanctum::actingAs($editor);
        $created = $this->postJson('/api/v1/editorial/posts', [
            'title' => 'Sicherer Vereinsalltag',
            'excerpt' => str_repeat('Sicher und verständlich. ', 6),
            'content' => "Erster Absatz.\n\n<script>alert(1)</script>",
            'blog_category_id' => $category->id,
            'tags' => ['Sicherheit', 'Verein', 'Sicherheit'],
            'status' => 'review',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.author.id', $editor->id);
        $postId = $created->json('data.id');

        $this->assertDatabaseHas('blog_post_revisions', [
            'blog_post_id' => $postId,
            'user_id' => $editor->id,
        ]);
        $this->assertDatabaseHas('blog_posts', [
            'id' => $postId,
            'status' => 'review',
        ]);
        $this->assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            (string) $this->app['db']->table('blog_posts')->where('id', $postId)->value('content'),
        );

        $qualityContent = implode(' ', array_fill(0, 500, 'Vereinswissen'));
        $this->putJson("/api/v1/editorial/posts/{$postId}", [
            'title' => 'Sicherer Vereinsalltag für moderne Sportorganisationen',
            'excerpt' => str_repeat('Praxiswissen für sichere digitale Vereinsarbeit. ', 3),
            'content' => $qualityContent,
            'cover_image' => 'https://cdn.example.test/cover.webp',
            'blog_category_id' => $category->id,
            'tags' => ['Sicherheit', 'Verein'],
            'meta_title' => 'Sicherer Vereinsalltag für moderne Sportvereine',
            'meta_description' => str_repeat('Sichere Vereinsarbeit mit verständlichen Regeln. ', 3),
            'status' => 'published',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.publisher.id', $editor->id);

        $this->assertDatabaseCount('blog_post_revisions', 2);
        $this->getJson('/api/v1/editorial/posts')
            ->assertOk()
            ->assertJsonPath('can.publish', true)
            ->assertJsonPath('data.0.id', $postId);

        $this->deleteJson("/api/v1/editorial/posts/{$postId}")->assertOk();
        $this->assertDatabaseMissing('blog_posts', ['id' => $postId]);
    }

    public function test_editorial_and_global_sponsor_management_reject_unauthorized_users(): void
    {
        $player = $this->withRole('player');
        $platformSponsor = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Global Partner',
        ]);

        Sanctum::actingAs($player);
        $this->getJson('/api/v1/editorial/posts')->assertForbidden();
        $this->postJson('/api/v1/editorial/posts', [])->assertForbidden();
        $this->getJson('/api/v1/sponsor-management')->assertForbidden();
        $this->deleteJson("/api/v1/sponsor-management/{$platformSponsor->id}")
            ->assertForbidden();
    }

    public function test_finance_manager_can_manage_global_sponsors_without_public_data_leak(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo($this->permissions(['finance.edit']));

        Sanctum::actingAs($manager);
        $created = $this->postJson('/api/v1/sponsor-management', [
            'scope' => 'platform',
            'name' => 'Airmius Hauptpartner',
            'contact_name' => 'Interne Ansprechpartnerin',
            'email' => 'intern@example.test',
            'website' => 'https://partner.example.test',
            'amount' => 30000,
            'logo_light' => 'https://cdn.example.test/logo-light.webp',
            'logo_dark' => 'https://cdn.example.test/logo-dark.webp',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'intern@example.test')
            ->assertJsonPath('data.amount', '30000.00');
        $sponsorId = $created->json('data.id');

        $this->putJson("/api/v1/sponsor-management/{$sponsorId}", [
            'scope' => 'outfit_subscription',
            'name' => 'Airmius Outfit Partner',
            'contact_name' => null,
            'email' => 'outfit@example.test',
            'website' => 'https://outfit.example.test',
            'amount' => 25000,
            'logo_light' => null,
            'logo_dark' => null,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonths(6)->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.scope', 'outfit_subscription');

        $public = $this->getJson('/api/v1/public/sponsors')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Airmius Outfit Partner');
        $this->assertArrayNotHasKey('email', $public->json('data.0'));
        $this->assertArrayNotHasKey('contact_name', $public->json('data.0'));
        $this->assertArrayNotHasKey('amount', $public->json('data.0'));

        $this->deleteJson("/api/v1/sponsor-management/{$sponsorId}")->assertOk();
    }

    public function test_club_owner_sees_only_own_club_sponsors_and_cannot_delete_platform_partner(): void
    {
        $owner = $this->withRole('club_owner');
        $otherOwner = $this->withRole('club_owner');
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $ownSponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'Eigener Vereinspartner',
        ]);
        Sponsor::query()->create([
            'club_id' => $otherClub->id,
            'scope' => 'club',
            'name' => 'Fremder Vereinspartner',
        ]);
        $platform = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Plattformpartner',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSponsor->id);
        $this->deleteJson("/api/v1/sponsor-management/{$platform->id}")
            ->assertForbidden();
    }

    private function permissions(array $names): array
    {
        return collect($names)
            ->map(fn (string $name) => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]))
            ->all();
    }

    private function withRole(string $name): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
