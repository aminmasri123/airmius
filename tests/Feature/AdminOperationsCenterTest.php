<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\CommerceOrder;
use App\Models\MailDelivery;
use App\Models\Permission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Services\AdminOperationsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_center_is_private_and_rejects_users_without_an_operations_capability(): void
    {
        $this->get(route('admin.operations.index'))->assertRedirect(route('login'));

        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.operations.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson(route('admin.operations.data', ['workspace' => 'platform']))
            ->assertForbidden();
    }

    public function test_workspaces_follow_least_privilege_capabilities(): void
    {
        $engineer = User::factory()->create();
        $this->grant($engineer, ['logs.view', 'api.manage']);

        $this->actingAs($engineer)
            ->get(route('admin.operations.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/Operations/Index')
                ->has('workspaces', 1)
                ->where('workspaces.0.key', 'platform')
                ->where('workspaces.0.access_mode', 'metadata')
            );

        $this->actingAs($engineer)
            ->getJson(route('admin.operations.data', ['workspace' => 'trust']))
            ->assertForbidden();

        $moderator = User::factory()->create();
        $this->grant($moderator, ['community.moderate']);

        $this->actingAs($moderator)
            ->get(route('admin.moderation.index'))
            ->assertOk();

        $this->actingAs($moderator)
            ->get(route('admin.operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspaces', 1)
                ->where('workspaces.0.key', 'trust')
            );

        $revenueOperator = User::factory()->create();
        $this->grant($revenueOperator, ['subscriptions.manage']);

        $this->actingAs($revenueOperator)
            ->get(route('admin.operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspaces', 1)
                ->where('workspaces.0.key', 'revenue')
            );
    }

    public function test_platform_projection_excludes_contact_free_text_and_raw_error_data(): void
    {
        $operator = User::factory()->create();
        $this->grant($operator, ['system.manage', 'support.tickets']);
        $requester = User::factory()->create([
            'name' => 'Sensitive Requester Name',
            'email' => 'sensitive-requester@example.test',
        ]);

        Club::query()->create([
            'name' => 'Sensitive Club Name',
            'owner_id' => $requester->id,
            'verification_status' => 'pending_verification',
            'verification_notes' => 'Sensitive verification notes',
            'verification_requested_at' => now()->subDays(4),
        ]);
        UserRoleApplication::query()->create([
            'user_id' => $requester->id,
            'type' => 'trainer',
            'status' => 'pending',
            'message' => 'Sensitive trainer application message',
            'application_data' => ['certification' => 'Sensitive certificate'],
            'requested_at' => now()->subDay(),
        ]);
        MailDelivery::query()->create([
            'dedupe_key' => 'operations-sensitive-mail',
            'mail_type' => 'invoice.created',
            'recipient_id' => $requester->id,
            'recipient_email' => $requester->email,
            'recipient_name' => $requester->name,
            'status' => 'failed',
            'error_message' => 'Sensitive SMTP error details',
            'context' => ['secret' => 'Sensitive raw context'],
        ]);
        SupportTicket::query()->create([
            'user_id' => $requester->id,
            'name' => $requester->name,
            'email' => $requester->email,
            'subject' => 'Sensitive support subject',
            'message' => 'Sensitive support message',
            'category' => 'privacy',
            'priority' => 'urgent',
            'status' => 'open',
            'response_due_at' => now()->subHour(),
            'due_at' => now()->addHour(),
            'admin_note' => 'Sensitive internal note',
        ]);

        $response = $this->actingAs($operator)
            ->getJson(route('admin.operations.data', ['workspace' => 'platform']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('data.workspace', 'platform')
            ->assertJsonPath('data.privacy.contains_personal_contact_data', false)
            ->assertJsonPath('data.privacy.contains_free_text', false)
            ->assertJsonPath('data.privacy.contains_raw_audit_payloads', false)
            ->assertJsonFragment(['kind' => 'club_verification'])
            ->assertJsonFragment(['kind' => 'trainer_application'])
            ->assertJsonFragment(['kind' => 'mail_delivery'])
            ->assertJsonFragment(['kind' => 'support_ticket']);

        $json = $response->getContent();
        foreach ([
            'Sensitive Requester Name',
            'sensitive-requester@example.test',
            'Sensitive Club Name',
            'Sensitive verification notes',
            'Sensitive trainer application message',
            'Sensitive certificate',
            'Sensitive SMTP error details',
            'Sensitive raw context',
            'Sensitive support subject',
            'Sensitive support message',
            'Sensitive internal note',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_revenue_projection_has_a_fixed_query_and_row_budget(): void
    {
        $operator = User::factory()->create();
        $this->grant($operator, ['subscriptions.manage', 'outfit-subscriptions.manage']);

        for ($index = 0; $index < 24; $index++) {
            CommerceOrder::query()->create([
                'user_id' => null,
                'type' => 'marketplace_product',
                'provider' => 'bank_transfer',
                'status' => 'pending',
                'issue_status' => 'reported',
                'issue_note' => 'Private issue '.$index,
                'issue_reported_at' => now()->subMinutes($index),
                'amount_cents' => 1000 + $index,
                'currency' => 'EUR',
            ]);
        }

        $service = app(AdminOperationsService::class);
        $service->workspaces($operator);
        $selectQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$selectQueries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selectQueries++;
            }
        });

        $payload = $service->payload($operator, AdminOperationsService::WORKSPACE_REVENUE);

        $this->assertLessThanOrEqual(6, $selectQueries);
        $this->assertCount(8, array_values(array_filter($payload['cases'], fn (array $case) => $case['kind'] === 'order_issue')));
        $this->assertTrue($payload['summary']['has_more']);
        $this->assertLessThanOrEqual(40, count($payload['cases']));
    }

    public function test_operations_vue_contract_uses_abortable_on_demand_loading_without_polling(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Admin/Operations/Index.vue'));

        $this->assertStringContainsString('new AbortController()', $source);
        $this->assertStringContainsString('\'X-Requested-With\': \'XMLHttpRequest\'', $source);
        $this->assertStringContainsString('cache[workspace]', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringNotContainsString('setInterval(', $source);
        $this->assertStringNotContainsString('setTimeout(', $source);
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
