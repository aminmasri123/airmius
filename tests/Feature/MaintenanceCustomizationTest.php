<?php
namespace Tests\Feature;
use App\Models\Setting;
use App\Models\User;
use App\Support\MaintenanceSettings;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class MaintenanceCustomizationTest extends TestCase
{
    use RefreshDatabase;
    private function payload(): array {
        return ['maintenance_enabled'=>false,'maintenance_title'=>'Update','maintenance_message'=>'Bald zurück','billing_payment_terms_days'=>14,'email_templates'=>['test'=>['subject'=>'Test','greeting'=>'Hallo','body'=>'Text']]];
    }
    public function test_customization_does_not_activate_and_old_clients_preserve_fields(): void {
        $this->seed(RolesPermissionsSeeder::class);
        $user=User::factory()->create(); $user->givePermissionTo('system.manage'); \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->actingAs($user)->putJson('/api/v1/admin/system/settings',array_merge($this->payload(),['maintenance_scenario'=>'update','maintenance_support_url'=>'https://example.com/status','maintenance_ends_at'=>'2026-12-01T10:00:00+01:00']))->assertOk();
        $this->assertFalse(MaintenanceSettings::data()['enabled']);
        $this->actingAs($user)->putJson('/api/v1/admin/system/settings',$this->payload())->assertOk();
        $this->assertSame('update',MaintenanceSettings::data()['scenario']);
        $this->assertSame('https://example.com/status',MaintenanceSettings::data()['support_url']);
    }
    public function test_invalid_urls_and_scenarios_are_rejected(): void {
        $this->seed(RolesPermissionsSeeder::class);
        $user=User::factory()->create(); $user->givePermissionTo('system.manage'); \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->actingAs($user)->putJson('/api/v1/admin/system/settings',array_merge($this->payload(),['maintenance_scenario'=>'invalid','maintenance_support_url'=>'javascript:alert(1)']))->assertUnprocessable()->assertJsonValidationErrors(['maintenance_scenario','maintenance_support_url']);
    }
}


