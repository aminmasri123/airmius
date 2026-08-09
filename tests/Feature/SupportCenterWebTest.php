<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupportCenterWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_the_private_support_center(): void
    {
        $this->get('/support')->assertRedirect(route('login'));
    }

    public function test_member_receives_only_linked_clubs_and_localized_private_workspace(): void
    {
        $member = User::factory()->create(['language' => 'ar']);
        $foreignOwner = User::factory()->create();
        $linked = Club::factory()->create(['owner_id' => $member->id, 'name' => 'Linked Club']);
        Club::factory()->create(['owner_id' => $foreignOwner->id, 'name' => 'Foreign Club']);

        $this->actingAs($member)
            ->get(route('auth.support.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Support/Index')
                ->where('clubs.0.id', $linked->id)
                ->where('clubs.0.name', 'Linked Club')
                ->count('clubs', 1)
                ->where('abilities.operate', true)
                ->where('abilities.cross_tenant', false)
                ->where('supportClubs.0.id', $linked->id)
                ->where('copy.title', trans('support.web.title', locale: 'ar'))
                ->where('options.categories.4.value', 'privacy'));
    }

    public function test_plain_member_without_tenant_management_has_no_operations_access(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('auth.support.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('abilities.operate', false)
                ->where('abilities.cross_tenant', false)
                ->count('clubs', 0)
                ->count('supportClubs', 0));
    }

    public function test_platform_support_gets_cross_tenant_operations_without_eager_club_data(): void
    {
        $support = User::factory()->create();
        Permission::findOrCreate('support.tickets', 'web');
        $support->givePermissionTo('support.tickets');
        Club::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($support)
            ->get(route('auth.support.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('abilities.operate', true)
                ->where('abilities.cross_tenant', true)
                ->count('supportClubs', 0));
    }

    public function test_support_catalogs_have_identical_keys_and_placeholders(): void
    {
        $catalogs = collect(['de', 'en', 'fr', 'ar'])->mapWithKeys(fn (string $locale) => [
            $locale => Arr::dot(require base_path("lang/{$locale}/support.php")),
        ]);
        $baseline = $catalogs->get('de');

        foreach ($catalogs as $locale => $catalog) {
            $this->assertSame(array_keys($baseline), array_keys($catalog), "Support keys differ for {$locale}.");
            foreach ($baseline as $key => $source) {
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $source, $sourcePlaceholders);
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $catalog[$key], $targetPlaceholders);
                $this->assertSame($sourcePlaceholders[0], $targetPlaceholders[0], "Support placeholders differ for {$locale}:{$key}.");
            }
        }
    }

    public function test_support_frontend_uses_abortable_ajax_and_safe_rendering_contracts(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Support/Index.vue'));
        $navigation = file_get_contents(resource_path('js/composables/useAirmiusShellNavigation.js'));
        $workspaces = file_get_contents(app_path('Http/Controllers/RoleWorkspaceController.php'));

        $this->assertStringContainsString('new AbortController()', $source);
        $this->assertStringContainsString("route('api.v1.support.tickets.index')", $source);
        $this->assertStringContainsString("route('api.v1.admin.support.tickets.update'", $source);
        $this->assertStringContainsString('onBeforeUnmount', $source);
        $this->assertStringNotContainsString('setInterval', $source);
        $this->assertStringNotContainsString('v-html', $source);
        $this->assertStringContainsString("route('auth.support.index')", $navigation);
        $this->assertStringContainsString("route('auth.support.index')", $workspaces);
    }
}
