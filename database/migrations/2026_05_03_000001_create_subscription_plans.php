<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedInteger('yearly_price_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('member_limit')->nullable();
            $table->unsignedInteger('team_limit')->nullable();
            $table->unsignedInteger('storage_gb')->default(1);
            $table->json('features')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('club_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();

            $table->unique('club_id');
        });

        $now = now();
        DB::table('subscription_plans')->insert([
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Kostenloser Einstieg für Sportler, Teams und kleine Vereine.',
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'member_limit' => 25,
                'team_limit' => 1,
                'storage_gb' => 1,
                'features' => json_encode(['Sportlerprofile', 'Basis-Feed', '1 Team/Verein', 'bis 25 Mitglieder', 'Basis-Chat', 'Trainings & Events Basis', 'Elternzustimmung']),
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'Für kleine Teams, Trainingsgruppen und Vereine mit einfachen Verwaltungsaufgaben.',
                'monthly_price_cents' => 900,
                'yearly_price_cents' => 9000,
                'member_limit' => 50,
                'team_limit' => 5,
                'storage_gb' => 1,
                'features' => json_encode(['Alles aus Free', 'bis 50 Mitglieder', 'mehrere Teams', 'Excel/CSV-Import', 'Mitglieds- und Lizenznummern', 'Basis-Rechnungen', 'E-Mail-Einladungen']),
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'club',
                'name' => 'Club',
                'description' => 'Der Kernplan für kleine und mittlere Sportvereine.',
                'monthly_price_cents' => 1900,
                'yearly_price_cents' => 19000,
                'member_limit' => 150,
                'team_limit' => null,
                'storage_gb' => 5,
                'features' => json_encode(['Alles aus Starter', 'bis 150 Mitglieder', 'unbegrenzte Teams', 'Beitragsverwaltung', 'Rechnungen', 'Zahlungshistorie', 'Mahnungen', 'Sponsorenverwaltung Basis']),
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Für größere Vereine mit mehreren Abteilungen und professioneller Verwaltung.',
                'monthly_price_cents' => 3900,
                'yearly_price_cents' => 39000,
                'member_limit' => 500,
                'team_limit' => null,
                'storage_gb' => 20,
                'features' => json_encode(['Alles aus Club', 'bis 500 Mitglieder', 'wiederkehrende Rechnungen vorbereitet', 'erweiterte Rollen', 'Sponsoren-Pakete', 'erweiterte Statistiken', 'Prioritaets-Support']),
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'elite',
                'name' => 'Elite',
                'description' => 'Für große Vereine, Leistungszentren und Organisationen mit hohen Ansprüchen.',
                'monthly_price_cents' => 7900,
                'yearly_price_cents' => 79000,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 100,
                'features' => json_encode(['Alles aus Pro', 'hohe oder unbegrenzte Mitgliederzahl', 'Mehrvereins-/Standortverwaltung vorbereitet', 'API-Zugang vorbereitet', 'Audit-Logs vorbereitet', 'Custom Rollen vorbereitet']),
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('club_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
