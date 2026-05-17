<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\LearningCourse;
use App\Models\MarketplaceSellerApplication;
use App\Models\MarketplaceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceProductSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_coach_can_open_marketplace_product_submission(): void
    {
        $coach = $this->freeCoach();

        $response = $this->actingAs($coach)->get(route('auth.commerce.index', ['tab' => 'create']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Dashboard/Commerce/Index'));
    }

    public function test_free_coach_can_submit_course_offer_without_premium(): void
    {
        $coach = $this->freeCoach();
        $course = LearningCourse::query()->create([
            'user_id' => $coach->id,
            'title' => 'Lauftechnik Academy',
            'slug' => 'lauftechnik-academy',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
        ]);

        $response = $this->actingAs($coach)->from(route('auth.commerce.index', ['tab' => 'create']))->post(route('auth.commerce.products.store'), [
            'learning_course_id' => $course->id,
            'title' => 'Lauftechnik Camp Free Test',
            'description' => 'Ein Trainingsangebot fuer bessere Lauftechnik.',
            'category' => 'course',
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'price_cents' => 1099,
            'tax_class' => 'standard',
            'digital_delivery_note' => 'Zugangsdaten werden per E-Mail versendet.',
            'course_outline_text' => "Warm-up\nTechnikdrills",
            'learning_goals_text' => "Effizienter laufen\nVerletzungen vermeiden",
        ]);

        $response->assertRedirect(route('auth.commerce.index', ['tab' => 'create']));

        $product = MarketplaceProduct::query()->where('title', 'Lauftechnik Camp Free Test')->first();

        $this->assertNotNull($product);
        $this->assertSame($coach->id, $product->user_id);
        $this->assertSame($course->id, $product->learning_course_id);
        $this->assertSame('course', $product->category);
        $this->assertSame('online_course', $product->offer_type);
        $this->assertSame('digital', $product->product_type);
        $this->assertFalse($product->is_shippable);
        $this->assertFalse($product->manages_stock);
        $this->assertSame(['Warm-up', 'Technikdrills'], $product->course_outline);
        $this->assertSame(['Effizienter laufen', 'Verletzungen vermeiden'], $product->learning_goals);
        $this->assertSame('published', $product->status);
        $this->assertSame(1099, $product->price_cents);
    }

    public function test_training_plan_offer_enables_coach_feedback_metadata(): void
    {
        $coach = $this->freeCoach();

        $this->actingAs($coach)->post(route('auth.commerce.products.store'), [
            'title' => '8 Wochen Sprintplan',
            'description' => 'Trainingsplan mit woechentlichem Feedback.',
            'category' => 'course',
            'offer_type' => 'training_plan',
            'product_type' => 'digital',
            'price_cents' => 4999,
            'coaching_enabled' => true,
            'coach_feedback_instructions' => 'Athleten senden jede Woche Bilder und kurze Belastungsnotizen.',
        ]);

        $product = MarketplaceProduct::query()->where('title', '8 Wochen Sprintplan')->first();

        $this->assertNotNull($product);
        $this->assertSame('training_plan', $product->offer_type);
        $this->assertSame('course', $product->category);
        $this->assertSame('digital', $product->product_type);
        $this->assertTrue($product->coaching_enabled);
        $this->assertSame('Athleten senden jede Woche Bilder und kurze Belastungsnotizen.', $product->coach_feedback_instructions);
    }

    public function test_e_learning_page_lists_published_learning_offers_without_stock(): void
    {
        $tutor = User::factory()->create();

        LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Videoanalyse Kurs',
            'slug' => 'videoanalyse-kurs',
            'description' => 'Digitaler Kurs fuer Spielanalyse.',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);

        $response = $this->get(route('guest.e-learning'));

        $response->assertOk();
        $response->assertSee('Videoanalyse Kurs');
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $coach = $this->freeCoach();

        $response = $this->actingAs($coach)->from(route('auth.commerce.index', ['tab' => 'create']))->post(route('auth.commerce.products.store'), [
            'category' => 'course',
        ]);

        $response->assertRedirect(route('auth.commerce.index', ['tab' => 'create']));
        $response->assertSessionHasErrors(['title', 'price_cents']);
        $this->assertSame(0, MarketplaceProduct::query()->count());
    }

    public function test_user_cannot_submit_offer_for_unrelated_club(): void
    {
        $coach = $this->freeCoach();
        $foreignOwner = User::factory()->create();
        $foreignClub = Club::factory()->create(['owner_id' => $foreignOwner->id]);

        $response = $this->actingAs($coach)->post(route('auth.commerce.products.store'), [
            'club_id' => $foreignClub->id,
            'title' => 'Fremder Vereinskurs',
            'description' => 'Soll nicht fuer fremde Vereine angelegt werden.',
            'category' => 'course',
            'product_type' => 'digital',
            'price_cents' => 1099,
        ]);

        $response->assertSessionHasErrors(['club_id']);
        $this->assertSame(0, MarketplaceProduct::query()->count());
    }

    public function test_regular_club_member_cannot_submit_offer_for_club(): void
    {
        $coach = $this->freeCoach();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($coach->id, ['role' => 'member']);

        $response = $this->actingAs($coach)->post(route('auth.commerce.products.store'), [
            'club_id' => $club->id,
            'title' => 'Mitglieder Vereinskurs',
            'description' => 'Normale Mitglieder duerfen nicht im Namen des Vereins verkaufen.',
            'category' => 'course',
            'product_type' => 'digital',
            'price_cents' => 1099,
        ]);

        $response->assertSessionHasErrors(['club_id']);
        $this->assertSame(0, MarketplaceProduct::query()->count());
    }

    public function test_guest_cannot_submit_marketplace_offer(): void
    {
        $response = $this->post(route('auth.commerce.products.store'), [
            'title' => 'Gast Kurs',
            'category' => 'course',
            'price_cents' => 1099,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, MarketplaceProduct::query()->count());
    }

    private function freeCoach(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'coach',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);
        MarketplaceSellerApplication::query()->create([
            'user_id' => $user->id,
            'applicant_type' => 'private',
            'accepted_rules' => ['product_truth', 'rights', 'commission'],
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        return $user;
    }
}
