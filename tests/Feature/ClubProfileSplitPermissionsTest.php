<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubProfileSplitPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_editor_can_only_update_general_club_fields(): void
    {
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_PROFILE_EDIT);
        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/clubs/{$club->id}", [
            'name' => 'Neuer Vereinsname',
            'city' => 'Köln',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Neuer Vereinsname')
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_edit_club_profile', true)
            ->assertJsonPath('data.can_edit_club_legal', false);

        $this->putJson("/api/v1/clubs/{$club->id}", [
            'name' => 'Nicht gespeichert',
            'tax_number' => '123/456',
        ])->assertForbidden();

        $this->assertSame('Neuer Vereinsname', $club->fresh()->name);
        $this->assertNull($club->fresh()->tax_number);
    }

    public function test_legal_editor_receives_and_updates_only_legal_master_data(): void
    {
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_LEGAL_EDIT, [
            'tax_number' => 'ALT',
            'contact_email' => 'privat@example.test',
            'letterhead_settings' => ['show_logo' => true, 'header' => 'Intern'],
        ]);
        Sanctum::actingAs($editor);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.tax_number', 'ALT')
            ->assertJsonMissingPath('data.contact_email')
            ->assertJsonMissingPath('data.letterhead_settings');

        $this->putJson("/api/v1/clubs/{$club->id}", ['tax_number' => 'NEU'])
            ->assertOk()
            ->assertJsonPath('data.tax_number', 'NEU');

        $this->putJson("/api/v1/clubs/{$club->id}", ['city' => 'Berlin'])
            ->assertForbidden();
    }

    public function test_contact_editor_receives_private_contacts_without_legal_or_branding_configuration(): void
    {
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_CONTACT_EDIT, [
            'contact_email' => 'privat@example.test',
            'contact_details_public' => false,
            'tax_number' => 'SECRET',
            'letterhead_settings' => ['show_logo' => true, 'header' => 'Intern'],
        ]);
        Sanctum::actingAs($editor);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.contact_email', 'privat@example.test')
            ->assertJsonPath('data.contact_details_public', false)
            ->assertJsonMissingPath('data.tax_number')
            ->assertJsonMissingPath('data.letterhead_settings');

        $this->putJson("/api/v1/clubs/{$club->id}", ['contact_phone' => '+49 221 123'])
            ->assertOk()
            ->assertJsonPath('data.contact_phone', '+49 221 123');
    }

    public function test_branding_editor_can_update_branding_and_images_only(): void
    {
        Storage::fake(UploadStorage::disk());
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_BRANDING_EDIT, [
            'tax_number' => 'SECRET',
            'letterhead_settings' => ['show_logo' => true, 'header' => 'Alt'],
        ]);
        Sanctum::actingAs($editor);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.letterhead_settings.header', 'Alt')
            ->assertJsonMissingPath('data.tax_number');

        $this->putJson("/api/v1/clubs/{$club->id}", ['brand_primary_color' => '#112233'])
            ->assertOk()
            ->assertJsonPath('data.brand_primary_color', '#112233');

        $this->post("/api/v1/clubs/{$club->id}/images", [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNotNull($club->fresh()->logo);
        $this->putJson("/api/v1/clubs/{$club->id}", ['contact_email' => 'no@example.test'])
            ->assertForbidden();
    }

    public function test_legacy_content_manager_keeps_all_profile_capabilities(): void
    {
        [$club, $manager] = $this->clubAndEditor(ClubPermissions::CONTENT_MANAGE);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit_club_profile', true)
            ->assertJsonPath('data.can_edit_club_legal', true)
            ->assertJsonPath('data.can_edit_club_contact', true)
            ->assertJsonPath('data.can_edit_club_branding', true);

        $this->putJson("/api/v1/clubs/{$club->id}", [
            'name' => 'Kompatibler Manager',
            'tax_number' => '123',
            'contact_email' => 'manager@example.test',
            'brand_primary_color' => '#ABCDEF',
        ])->assertOk();

        $club->users()->updateExistingPivot($manager->id, [
            'permission_overrides' => [
                ClubPermissions::CONTENT_MANAGE => true,
                ClubPermissions::CLUB_LEGAL_EDIT => false,
            ],
        ]);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit_club_profile', true)
            ->assertJsonPath('data.can_edit_club_legal', false)
            ->assertJsonMissingPath('data.tax_number');
    }

    public function test_web_profile_exposes_only_the_authorized_editor_section(): void
    {
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_CONTACT_EDIT, [
            'contact_email' => 'intern@example.test',
            'contact_details_public' => false,
            'tax_number' => 'SECRET',
            'letterhead_settings' => ['show_logo' => true, 'header' => 'Intern'],
        ]);

        $this->actingAs($editor)
            ->get(route('auth.clubs.show', $club))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewer.can_manage', false)
                ->where('viewer.can_edit_club_profile', false)
                ->where('viewer.can_edit_club_legal', false)
                ->where('viewer.can_edit_club_contact', true)
                ->where('viewer.can_edit_club_branding', false)
                ->where('clubProfile.contact_email', 'intern@example.test')
                ->missing('clubProfile.tax_number')
                ->missing('clubProfile.letterhead_settings'));
    }

    public function test_web_profile_does_not_use_generic_management_to_bypass_explicit_denials(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'tax_number' => 'INTERN',
            'letterhead_settings' => ['show_logo' => true, 'header' => 'Intern'],
        ]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::CLUB_LEGAL_EDIT => false,
                ClubPermissions::CLUB_BRANDING_EDIT => false,
                ClubPermissions::MEMBERS_ROLES => false,
            ],
        ]);

        $this->actingAs($manager)
            ->get(route('auth.clubs.show', $club))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewer.can_manage', true)
                ->where('viewer.can_edit_club_profile', true)
                ->where('viewer.can_edit_club_legal', false)
                ->where('viewer.can_edit_club_branding', false)
                ->where('viewer.can_manage_roles', false)
                ->missing('clubProfile.tax_number')
                ->missing('clubProfile.letterhead_settings'));

        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Clubs/Profile.vue'));
        $this->assertStringContainsString(
            'const canEditClubLegal = computed(() => props.viewer.can_edit_club_legal === true)',
            $source,
        );
        $this->assertStringContainsString(
            'const canManageClubRoles = computed(() => props.viewer.can_manage_roles === true)',
            $source,
        );
        $this->assertStringNotContainsString(
            'props.viewer.can_edit_club_legal === true || props.viewer.can_manage === true',
            $source,
        );

        $mobileSource = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/clubs_screen.dart'));
        $this->assertSame(4, substr_count($mobileSource, 'canEditClubBranding'));
        $this->assertStringContainsString(
            'profileClub.canEditClubBranding && !_uploadingCover',
            $mobileSource,
        );
        $this->assertStringContainsString(
            'profileClub.canEditClubBranding && !_uploadingLogo',
            $mobileSource,
        );
        $this->assertStringNotContainsString(
            'profileClub.canManage && !_uploadingCover',
            $mobileSource,
        );
        $this->assertStringNotContainsString(
            'profileClub.canManage && !_uploadingLogo',
            $mobileSource,
        );
    }

    private function clubAndEditor(string $permission, array $clubAttributes = []): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'country' => 'DE',
            ...$clubAttributes,
        ]);
        $club->users()->attach($editor->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [$permission => true],
        ]);

        return [$club, $editor];
    }
}
