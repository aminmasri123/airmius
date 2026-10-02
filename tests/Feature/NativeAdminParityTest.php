<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\ContentReport;
use App\Models\ModerationFlag;
use App\Models\OperatingContract;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NativeAdminParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_specialist_gets_only_role_data_and_cannot_escalate_permissions(): void
    {
        $this->actingWith('users.assign_roles');
        $this->getJson('/api/v1/admin/platform')->assertOk()
            ->assertJsonPath('data.abilities.roles_assign', true)
            ->assertJsonPath('data.abilities.system_manage', false)
            ->assertJsonPath('data.abilities.moderation_manage', false)
            ->assertJsonPath('data.summary.users', null)
            ->assertJsonPath('data.summary.warnings_90_days', null)
            ->assertJsonCount(0, 'data.users')
            ->assertJsonCount(0, 'data.clubs')
            ->assertJsonCount(0, 'data.sports')
            ->assertJsonCount(0, 'data.badges')
            ->assertJsonCount(0, 'data.gamification_rules')
            ->assertJsonCount(0, 'data.moderation.flags');

        $roleId = $this->postJson('/api/v1/admin/platform/roles', [
            'name' => 'native_parity_reader', 'permissions' => [],
        ])->assertCreated()->json('data.id');
        Permission::findOrCreate('system.manage', 'web');
        $this->patchJson("/api/v1/admin/platform/roles/{$roleId}", [
            'permissions' => ['system.manage'],
        ])->assertForbidden();
        $this->assertFalse(Role::findOrFail($roleId)->hasPermissionTo('system.manage'));
        $protected = Role::findOrCreate('super_admin', 'web');
        $this->patchJson("/api/v1/admin/platform/roles/{$protected->id}", [
            'permissions' => [],
        ])->assertForbidden();
        $this->postJson('/api/v1/admin/platform/permissions', ['name' => 'native.test'])->assertForbidden();
        $this->postJson('/api/v1/admin/platform/sports', [])->assertForbidden();
        $this->deleteJson("/api/v1/admin/platform/roles/{$roleId}")->assertOk();
    }

    public function test_moderation_specialist_can_review_and_decide_appeals_but_cannot_manage_roles(): void
    {
        $moderator = $this->actingWith('moderation.manage');
        $post = Post::factory()->create(['user_id' => $moderator->id]);
        $flag = ModerationFlag::query()->create([
            'user_id' => $post->user_id, 'flaggable_type' => Post::class,
            'flaggable_id' => $post->id, 'reason' => 'Review', 'status' => 'open',
        ]);
        $report = ContentReport::query()->create([
            'reporter_id' => $moderator->id, 'reportable_type' => Post::class,
            'reportable_id' => $post->id, 'reason' => 'Review', 'status' => 'open',
            'appeal_status' => 'pending',
        ]);
        $this->getJson('/api/v1/admin/platform')->assertOk()
            ->assertJsonPath('data.abilities.moderation_manage', true)
            ->assertJsonCount(0, 'data.users')
            ->assertJsonCount(0, 'data.roles')
            ->assertJsonCount(0, 'data.permission_groups')
            ->assertJsonPath('data.moderation.flags.0.id', $flag->id);
        $this->patchJson("/api/v1/admin/platform/moderation/flags/{$flag->id}", [
            'status' => 'dismissed', 'decision_reason' => 'Allowed content',
        ])->assertOk();
        $this->patchJson("/api/v1/admin/platform/moderation/reports/{$report->id}", [
            'status' => 'dismissed',
        ])->assertOk();
        $this->patchJson("/api/v1/admin/platform/moderation/reports/{$report->id}/appeal", [
            'appeal_status' => 'accepted', 'appeal_decision' => 'Appeal accepted',
        ])->assertOk();
        $this->postJson('/api/v1/admin/platform/roles', [])->assertForbidden();

        $this->actingWith('users.assign_roles');
        $this->patchJson("/api/v1/admin/platform/moderation/flags/{$flag->id}", ['status' => 'open'])->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/platform')->assertForbidden();
        $this->patchJson("/api/v1/admin/platform/moderation/reports/{$report->id}", ['status' => 'open'])->assertForbidden();
        $this->patchJson("/api/v1/admin/platform/moderation/reports/{$report->id}/appeal", [])->assertForbidden();
    }

    public function test_plain_text_metadata_save_preserves_existing_rich_content_and_revision(): void
    {
        $editor = $this->actingWith('blog.view', 'blog.update');
        $html = '<h2>Heading</h2><p>A <strong>bold</strong> paragraph.</p><figure><img src="https://example.test/photo.jpg" alt="Photo"><figcaption>Caption</figcaption></figure>';
        $post = BlogPost::query()->create([
            'title' => 'Rich post', 'slug' => 'rich-native-parity', 'author_id' => $editor->id,
            'content' => $html, 'status' => 'draft',
        ]);
        $body = $this->getJson('/api/v1/editorial/posts?q=Rich%20post')->assertOk()->json('data.0.content_text');
        $this->putJson("/api/v1/editorial/posts/{$post->id}", [
            'title' => 'Updated title', 'content' => $body, 'status' => 'draft',
        ])->assertOk();
        $this->assertSame($html, $post->fresh()->content);
        $this->assertSame($html, $post->revisions()->latest('id')->firstOrFail()->content);
        $this->putJson("/api/v1/editorial/posts/{$post->id}", [
            'title' => 'Updated title', 'content' => '<script>alert(1)</script>', 'status' => 'draft',
        ])->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $post->fresh()->content);
    }

    public function test_editorial_pagination_filters_and_stable_order_reach_older_posts(): void
    {
        $editor = $this->actingWith('blog.view');
        for ($i = 0; $i < 32; $i++) {
            BlogPost::query()->create([
                'title' => "Parity article {$i}", 'slug' => "parity-article-{$i}",
                'author_id' => $editor->id, 'content' => '<p>Body</p>', 'status' => 'draft',
            ]);
        }
        $first = $this->getJson('/api/v1/editorial/posts?q=Parity%20article&status=draft')->assertOk()
            ->assertJsonCount(30, 'data')->assertJsonPath('meta.total', 32)->json('data');
        $second = $this->getJson('/api/v1/editorial/posts?q=Parity%20article&status=draft&page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2)->json('data');
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->getJson('/api/v1/editorial/posts?q=Parity%20article&status=review')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/editorial/posts?page=0')->assertUnprocessable();
    }

    public function test_contract_pages_and_filters_keep_global_summary_and_read_only_authorization(): void
    {
        $this->actingWith('finance.view');
        for ($i = 0; $i < 23; $i++) {
            OperatingContract::query()->create([
                'name' => "Parity contract {$i}", 'vendor' => 'Parity vendor', 'category' => 'other',
                'status' => 'active', 'amount' => 10, 'currency' => 'EUR', 'billing_interval' => 'monthly',
                'contract_number' => "REF-{$i}",
            ]);
        }
        $first = $this->getJson('/api/v1/admin/backoffice?contracts_q=Parity')->assertOk()
            ->assertJsonCount(20, 'data.contracts')->assertJsonPath('data.contracts_meta.total', 23)
            ->assertJsonPath('data.summary.contracts', 23)->assertJsonPath('data.summary.monthly_contract_cost_cents', 23000)
            ->json('data.contracts');
        $second = $this->getJson('/api/v1/admin/backoffice?contracts_q=Parity&contracts_page=2')->assertOk()
            ->assertJsonCount(3, 'data.contracts')->json('data.contracts');
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->getJson('/api/v1/admin/backoffice?contracts_q=REF-22&contracts_status=active&contracts_category=other')->assertOk()
            ->assertJsonCount(1, 'data.contracts')->assertJsonPath('data.summary.contracts', 23);
        $this->getJson('/api/v1/admin/backoffice?contracts_status=cancelled')->assertOk()->assertJsonCount(0, 'data.contracts');
        $this->getJson('/api/v1/admin/backoffice?contracts_page=0')->assertUnprocessable();
        $this->getJson('/api/v1/admin/backoffice?contracts_category=invalid')->assertUnprocessable();
        $this->postJson('/api/v1/admin/backoffice/contracts', [])->assertForbidden();
        $this->actingWith('subscriptions.manage');
        $this->getJson('/api/v1/admin/backoffice')->assertOk()->assertJsonCount(0, 'data.contracts')
            ->assertJsonPath('data.contracts_meta', null)->assertJsonPath('data.summary.contracts', 0);
    }

    private function actingWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Sanctum::actingAs($user);

        return $user;
    }
}
