<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ClubPermissions;
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

    public function test_club_sponsor_api_uses_separate_edit_and_delete_permissions(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $blockedManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $editor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_EDIT => true],
            ],
            $deleter->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_DELETE => true],
            ],
            $blockedManager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_DELETE => false],
            ],
        ]);
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'club'],
            [
                'target_actor' => 'verein', 'name' => 'Club Sponsor API',
                'monthly_price_cents' => 2990, 'yearly_price_cents' => 29900,
                'currency' => 'EUR', 'features' => [], 'sort_order' => 1,
                'is_public' => true, 'is_active' => true,
            ],
        );
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);
        $sponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'Getrennter Partner',
        ]);

        Sanctum::actingAs($editor);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('clubs.0.can_edit_sponsors', true)
            ->assertJsonPath('clubs.0.can_delete_sponsors', false)
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.can_delete', false)
            ->assertJsonPath('can.create', true)
            ->assertJsonPath('can.create_global', false);
        $this->putJson("/api/v1/sponsor-management/{$sponsor->id}", [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Bearbeiteter Partner',
        ])->assertOk();
        $this->deleteJson("/api/v1/sponsor-management/{$sponsor->id}")->assertForbidden();

        Sanctum::actingAs($blockedManager);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson("/api/v1/sponsor-management/{$sponsor->id}")->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('clubs.0.can_edit_sponsors', false)
            ->assertJsonPath('clubs.0.can_delete_sponsors', true)
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_delete', true)
            ->assertJsonPath('can.create', false);
        $this->putJson("/api/v1/sponsor-management/{$sponsor->id}", [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Nicht erlaubt',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/sponsor-management/{$sponsor->id}")->assertOk();
    }

    public function test_sponsor_mutations_follow_the_request_locale_and_catalogs_match(): void
    {
        $reference = require lang_path('de/sponsor.php');
        foreach (['en', 'fr', 'ar'] as $locale) {
            $catalog = require lang_path("{$locale}/sponsor.php");
            $this->assertSame(array_keys($reference), array_keys($catalog));
            $this->assertSame(
                array_keys($reference['flash']),
                array_keys($catalog['flash']),
            );
        }

        $manager = User::factory()->create(['language' => 'ar']);
        $manager->givePermissionTo($this->permissions(['finance.edit']));
        Sanctum::actingAs($manager);

        $this->withHeader('X-App-Locale', 'ar')
            ->postJson('/api/v1/sponsor-management', [
                'scope' => 'platform',
                'name' => 'Arabic Sponsor',
            ])
            ->assertCreated()
            ->assertJsonPath('message', trans('sponsor.flash.created', locale: 'ar'));
    }

    public function test_global_manager_can_update_and_delete_a_club_sponsor(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo($this->permissions(['finance.edit']));
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $sponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'QA Club Partner',
        ]);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sponsor->id);
        $this->putJson("/api/v1/sponsor-management/{$sponsor->id}", [
            'scope' => 'platform',
            'name' => 'QA Updated Partner',
        ])->assertOk()->assertJsonPath('data.name', 'QA Updated Partner');
        $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id, 'club_id' => null]);

        // Exercise deletion while it still belongs to a club, independently of update.
        $sponsor->refresh()->update(['scope' => 'club', 'club_id' => $club->id]);
        $this->deleteJson("/api/v1/sponsor-management/{$sponsor->id}")->assertOk();
        $this->assertDatabaseMissing('sponsors', ['id' => $sponsor->id]);
    }

    public function test_club_owner_cannot_move_a_sponsor_to_an_unmanaged_scope(): void
    {
        $owner = $this->withRole('club_owner');
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $sponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'QA Original Partner',
        ]);

        Sanctum::actingAs($owner);
        foreach ([
            ['scope' => 'platform'],
            ['scope' => 'outfit_subscription'],
            ['scope' => 'club', 'club_id' => $foreignClub->id],
        ] as $target) {
            $this->putJson("/api/v1/sponsor-management/{$sponsor->id}", $target + [
                'name' => 'QA Unauthorized Change',
            ])->assertForbidden();
            $this->assertDatabaseHas('sponsors', [
                'id' => $sponsor->id,
                'club_id' => $club->id,
                'scope' => 'club',
                'name' => 'QA Original Partner',
            ]);
        }
    }

    public function test_editor_without_publish_permission_cannot_publish_or_create_revisions_by_failed_writes(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo($this->permissions(['blog.view', 'blog.create', 'blog.update']));
        Sanctum::actingAs($editor);
        $payload = [
            'title' => 'QA Editorial Permission Boundary',
            'content' => 'Private QA editorial content.',
            'status' => 'review',
        ];
        $created = $this->postJson('/api/v1/editorial/posts', $payload)->assertCreated();
        $id = $created->json('data.id');
        $this->getJson('/api/v1/editorial/posts')->assertOk()->assertJsonPath('can.publish', false);
        $this->postJson('/api/v1/editorial/posts', array_replace($payload, ['status' => 'published']))
            ->assertForbidden();
        $this->putJson("/api/v1/editorial/posts/{$id}", array_replace($payload, ['status' => 'published']))
            ->assertForbidden();
        $this->deleteJson("/api/v1/editorial/posts/{$id}")->assertForbidden();
        $this->assertDatabaseHas('blog_posts', ['id' => $id, 'status' => 'review', 'published_by' => null]);
        $this->assertDatabaseCount('blog_posts', 1);
        $this->assertDatabaseCount('blog_post_revisions', 1);
    }

    public function test_mobile_public_blog_hides_drafts_review_archived_and_future_posts(): void
    {
        foreach (['draft', 'review', 'archived', 'published'] as $status) {
            $post = BlogPost::factory()->create([
                'status' => $status,
                'title' => "QA hidden {$status}",
                'published_at' => $status === 'published' ? now()->addDay() : null,
            ]);
            $this->getJson("/api/v1/public/blog/{$post->slug}")->assertNotFound();
        }
        $visible = BlogPost::factory()->published()->create([
            'title' => 'QA visible article',
            'published_at' => now()->subMinute(),
        ]);
        $response = $this->getJson('/api/v1/public/blog')->assertOk();
        $response->assertSee('QA visible article')->assertDontSee('QA hidden');
        $this->getJson("/api/v1/public/blog/{$visible->slug}")->assertOk();
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
