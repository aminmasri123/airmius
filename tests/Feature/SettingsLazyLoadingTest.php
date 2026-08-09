<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ConnectedSportAccount;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsLazyLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_settings_request_omits_every_expensive_tab_payload(): void
    {
        $user = User::factory()->create();
        $selects = [];
        DB::listen(function ($query) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selects[] = strtolower($query->sql);
            }
        });

        $this->actingAs($user)
            ->get(route('auth.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Settings/Index')
                ->where('activeSettingsTab', 'profile')
                ->has('profileAddress')
                ->has('privacySettings')
                ->missing('sports')
                ->missing('sportProfiles')
                ->missing('billingHistory')
                ->missing('currentUserSubscriptions')
                ->missing('privacyProviders')
                ->missing('socialAccounts')
                ->missing('sportIntegrations')
                ->missing('userRoles')
                ->missing('roleApplications')
                ->missing('activities'));

        $sql = implode("\n", $selects);
        $this->assertStringNotContainsString(' from "invoices"', $sql);
        $this->assertStringNotContainsString(' from "payments"', $sql);
        $this->assertStringNotContainsString(' from "sports"', $sql);
        $this->assertStringNotContainsString(' from "connected_sport_', $sql);
        $this->assertStringNotContainsString(' from "activities"', $sql);
    }

    public function test_direct_tab_links_include_only_their_required_payloads(): void
    {
        $user = User::factory()->create();
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-'.$user->id,
        ]);
        ConnectedSportAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'strava',
            'status' => 'connected',
        ]);

        $this->actingAs($user)
            ->get(route('auth.settings', ['tab' => 'privacy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeSettingsTab', 'privacy')
                ->where('privacyProviders.summary.total', 2)
                ->has('privacyProviders.items', 2)
                ->missing('billingHistory')
                ->missing('socialAccounts')
                ->missing('sportIntegrations')
                ->missing('sports'));

        $this->actingAs($user)
            ->get(route('auth.settings', ['tab' => 'billing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeSettingsTab', 'billing')
                ->has('billingHistory')
                ->has('currentUserSubscriptions')
                ->missing('privacyProviders')
                ->missing('sportIntegrations')
                ->missing('sports'));
    }

    public function test_partial_tab_request_returns_only_requested_settings_props(): void
    {
        $user = User::factory()->create();
        $assetVersion = app(HandleInertiaRequests::class)->version(request());

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $assetVersion,
                'X-Inertia-Partial-Component' => 'Auth/Dashboard/Settings/Index',
                'X-Inertia-Partial-Data' => 'activeSettingsTab,sports,userRoles,roleApplications',
            ])
            ->get(route('auth.settings', ['tab' => 'roles']))
            ->assertOk()
            ->assertJsonPath('component', 'Auth/Dashboard/Settings/Index')
            ->assertJsonPath('props.activeSettingsTab', 'roles')
            ->assertJsonStructure(['props' => ['sports', 'userRoles', 'roleApplications']])
            ->assertJsonMissingPath('props.profileAddress')
            ->assertJsonMissingPath('props.billingHistory')
            ->assertJsonMissingPath('props.sportIntegrations')
            ->assertJsonMissingPath('props.activities');
    }

    public function test_partial_reload_does_not_resolve_unrequested_props_of_the_active_tab(): void
    {
        $user = User::factory()->create();
        $assetVersion = app(HandleInertiaRequests::class)->version(request());
        $selects = [];
        DB::listen(function ($query) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selects[] = strtolower($query->sql);
            }
        });

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $assetVersion,
                'X-Inertia-Partial-Component' => 'Auth/Dashboard/Settings/Index',
                'X-Inertia-Partial-Data' => 'activeSettingsTab,userRoles',
            ])
            ->get(route('auth.settings', ['tab' => 'roles']))
            ->assertOk()
            ->assertJsonPath('props.activeSettingsTab', 'roles')
            ->assertJsonStructure(['props' => ['userRoles']])
            ->assertJsonMissingPath('props.sports')
            ->assertJsonMissingPath('props.roleApplications');

        $sql = implode("\n", $selects);
        $this->assertStringNotContainsString(' from "sports"', $sql);
        $this->assertStringNotContainsString(' from "user_role_applications"', $sql);
    }

    public function test_settings_client_caches_loaded_tabs_and_exposes_recovery_states(): void
    {
        $source = File::get(resource_path('js/Pages/Auth/Dashboard/Settings/Index.vue'));

        $this->assertStringContainsString("billing: ['billingHistory', 'currentUserSubscriptions']", $source);
        $this->assertStringContainsString("privacy: ['privacyProviders']", $source);
        $this->assertStringContainsString('.filter((prop) => !loadedTabProps.value.has(prop))', $source);
        $this->assertStringContainsString("only: ['activeSettingsTab', ...missingProps]", $source);
        $this->assertStringContainsString('loadedTabs.value.has(tab)', $source);
        $this->assertStringContainsString('preserveState: true', $source);
        $this->assertStringContainsString('pendingTab.value = tab', $source);
        $this->assertStringContainsString('retryFailedTab', $source);
        $this->assertStringContainsString("t('search.loading')", $source);
        $this->assertStringContainsString("t('global_feedback.unexpected')", $source);
    }
}
