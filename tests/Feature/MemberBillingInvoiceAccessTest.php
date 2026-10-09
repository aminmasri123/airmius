<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberBillingInvoiceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $member, array $attributes = []): Invoice
    {
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        return Invoice::create(array_merge([
            'club_id' => $club->id, 'user_id' => $member->id,
            'number' => 'MEM-'.uniqid(), 'title' => 'Jahresbeitrag', 'amount' => '36.00',
            'status' => 'open', 'issued_at' => '2026-10-09', 'due_date' => '2026-10-31',
            'billing_period_start' => '2026-01-01', 'billing_period_end' => '2026-12-31',
        ], $attributes));
    }

    public function test_member_lists_and_downloads_only_own_invoices_and_partial_balance(): void
    {
        $member = User::factory()->create();
        $own = $this->invoiceFor($member);
        $other = $this->invoiceFor(User::factory()->create());
        Payment::create(['club_id' => $own->club_id, 'user_id' => $member->id,
            'invoice_id' => $own->id, 'amount' => '10.00', 'status' => 'paid',
            'method' => 'cash', 'paid_at' => '2026-10-09']);
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.outstanding_amount', '26.00');
        $this->getJson("/api/v1/billing/invoices/{$own->id}?kind=club_invoice")->assertOk();
        $pdf = $this->get("/api/v1/billing/invoices/club_invoice/{$own->id}/download")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        if ($path = getenv('AIRMIUS_TEST_INVOICE_PDF')) {
            file_put_contents($path, $pdf->getContent());
        }
        $this->getJson("/api/v1/billing/invoices/{$other->id}?kind=club_invoice")->assertNotFound();
        $this->getJson("/api/v1/billing/invoices/club_invoice/{$other->id}/download")->assertNotFound();
        $this->postJson("/api/v1/billing/invoices/club_invoice/{$other->id}/question", ['message' => 'Wrong invoice'])->assertNotFound();
    }

    public function test_linked_external_member_and_beneficiary_can_download_but_email_match_cannot(): void
    {
        $member = User::factory()->create();
        $invoice = $this->invoiceFor(User::factory()->create());
        $external = ClubExternalMember::create(['club_id' => $invoice->club_id,
            'name' => $member->name, 'email' => $member->email, 'linked_user_id' => $member->id]);
        $invoice->update(['user_id' => null, 'club_external_member_id' => $external->id]);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/billing/invoices/club_invoice/{$invoice->id}/download")->assertOk();
        $external->update(['linked_user_id' => null]);
        $this->getJson("/api/v1/billing/invoices/club_invoice/{$invoice->id}/download")->assertNotFound();
        $invoice->update(['membership_user_id' => $member->id]);
        $this->getJson("/api/v1/billing/invoices/club_invoice/{$invoice->id}/download")->assertOk();
    }

    public function test_invoice_kind_keeps_subscription_and_club_ids_separate(): void
    {
        $member = User::factory()->create();
        $clubInvoice = $this->invoiceFor($member);
        $plan = SubscriptionPlan::create(['slug' => 'member-test', 'name' => 'Premium', 'target_actor' => 'sportler', 'monthly_price_cents' => 900, 'yearly_price_cents' => 9000, 'currency' => 'EUR']);
        $subscription = SubscriptionInvoice::create(['user_id' => $member->id, 'subscription_plan_id' => $plan->id, 'number' => 'SUB-1',
            'amount_cents' => 900, 'currency' => 'EUR', 'status' => 'paid', 'title' => 'Premium']);
        $this->assertSame($clubInvoice->id, $subscription->id);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/billing/invoices/{$subscription->id}?kind=subscription_invoice")
            ->assertOk()->assertJsonPath('data.number', 'SUB-1');
        $this->getJson("/api/v1/billing/invoices/subscription_invoice/{$subscription->id}/download")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $subscription->update(['user_id' => User::factory()->create()->id]);
        $this->getJson("/api/v1/billing/invoices/subscription_invoice/{$subscription->id}/download")->assertNotFound();
        $this->getJson("/api/v1/billing/invoices/club_invoice/{$clubInvoice->id}/download")->assertOk();
    }

    public function test_question_is_validated_logged_and_sent_to_club_responsible_people(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $invoice = $this->invoiceFor($member);
        Sanctum::actingAs($member);
        $path = "/api/v1/billing/invoices/club_invoice/{$invoice->id}/question";
        $this->postJson($path, ['message' => ''])->assertUnprocessable();
        $this->postJson($path, ['message' => 'Bitte meinen Beitrag pruefen.'])->assertOk();
        $this->assertDatabaseHas('activities', ['type' => 'club.invoice.member_question',
            'user_id' => $member->id, 'subject_id' => $invoice->id]);
        $entry = Activity::where('type', 'club.invoice.member_question')->firstOrFail();
        $this->assertSame('Bitte meinen Beitrag pruefen.', $entry->data['message']);
        $this->assertDatabaseHas('notifications', ['user_id' => $invoice->club->owner_id, 'type' => 'club.invoice.member_question']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $member->id, 'type' => 'club.invoice.member_question']);
    }

    public function test_web_download_has_the_same_ownership_and_guest_protection(): void
    {
        $member = User::factory()->create();
        $own = $this->invoiceFor($member);
        $other = $this->invoiceFor(User::factory()->create());
        $url = route('auth.billing.invoices.download', ['kind' => 'club_invoice', 'invoice' => $own->id]);
        $this->get($url)->assertRedirect();
        $this->actingAs($member)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('auth.billing.invoices.download', ['kind' => 'club_invoice', 'invoice' => $other->id]))->assertNotFound();
    }

    public function test_personal_invoice_pages_return_correct_pagination(): void
    {
        $member = User::factory()->create();
        $this->invoiceFor($member);
        $this->invoiceFor($member);
        Sanctum::actingAs($member);
        $first = $this->getJson('/api/v1/billing/invoices?per_page=1&page=1')->assertOk()
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 2);
        $second = $this->getJson('/api/v1/billing/invoices?per_page=1&page=2')->assertOk()
            ->assertJsonPath('meta.current_page', 2)->assertJsonCount(1, 'data');
        $this->assertNotSame($first->json('data.0.id'), $second->json('data.0.id'));
    }

    public function test_personal_endpoint_requires_login_and_does_not_grant_club_owner_blanket_access(): void
    {
        $invoice = $this->invoiceFor(User::factory()->create());
        $url = "/api/v1/billing/invoices/club_invoice/{$invoice->id}/download";
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($invoice->club->owner);
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_existing_protected_club_download_accepts_owner_member_but_not_another_member(): void
    {
        $member = User::factory()->create();
        $invoice = $this->invoiceFor($member);
        Sanctum::actingAs($member);
        $path = "/api/v1/clubs/{$invoice->club_id}/membership-invoices/{$invoice->id}";
        $response = $this->postJson($path.'/download-authorizations')->assertOk();
        $this->get($response->json('data.url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $other = User::factory()->create();
        $invoice->club->users()->attach($other->id, ['role' => 'member', 'membership_status' => 'active']);
        Sanctum::actingAs($other);
        $this->postJson($path.'/download-authorizations')->assertForbidden();
        $this->getJson($response->json('data.url'))->assertForbidden();
    }
}
