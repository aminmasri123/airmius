<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'target_actor')) {
                $table->string('target_actor', 30)->default('verein')->after('slug')->index();
            }

            if (! Schema::hasColumn('subscription_plans', 'cta_label')) {
                $table->string('cta_label')->nullable()->after('features');
            }

            if (! Schema::hasColumn('subscription_plans', 'badge')) {
                $table->string('badge')->nullable()->after('cta_label');
            }
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subscription_plan_id']);
        });

        $now = now();

        DB::table('subscription_plans')->whereIn('slug', ['free', 'starter', 'club', 'pro', 'elite'])->update([
            'target_actor' => 'verein',
            'updated_at' => $now,
        ]);

        $plans = [
            [
                'slug' => 'sportler-free',
                'target_actor' => 'sportler',
                'name' => 'Sportler Free',
                'description' => 'Kostenloses Sportprofil für Training, Teams, Community und Zahlungsverlauf.',
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 1,
                'features' => ['Sportprofil', 'Skills und Lizenznummer', 'Feed und Community', 'Teambeitritt per Anfrage', 'Chat Basis', 'Elternzustimmung unter 16'],
                'cta_label' => 'Kostenlos starten',
                'badge' => 'Fuer alle',
                'sort_order' => 110,
            ],
            [
                'slug' => 'sportler-pro',
                'target_actor' => 'sportler',
                'name' => 'Sportler Pro',
                'description' => 'Optionales Profil-Upgrade für Sichtbarkeit, Portfolio und sportliche Entwicklung.',
                'monthly_price_cents' => 400,
                'yearly_price_cents' => 4000,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 5,
                'features' => ['Erweitertes Profil', 'Portfolio und Medien', 'Profilstatistiken vorbereitet', 'Sichtbarkeitsboost vorbereitet', 'Bewerbungsmappe vorbereitet'],
                'cta_label' => 'Pro vormerken',
                'badge' => 'Später',
                'sort_order' => 120,
            ],
            [
                'slug' => 'trainer-free',
                'target_actor' => 'trainer',
                'name' => 'Trainer im Verein',
                'description' => 'Trainer arbeiten kostenlos innerhalb eines Vereins- oder Teamplans mit.',
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 1,
                'features' => ['Teamkommunikation', 'Trainingsplanung im Verein', 'Anwesenheit Basis', 'Feedback und Empfehlungen', 'Dateien im Vereinsspeicher'],
                'cta_label' => 'Als Trainer starten',
                'badge' => 'Im Verein kostenlos',
                'sort_order' => 210,
            ],
            [
                'slug' => 'trainer-pro',
                'target_actor' => 'trainer',
                'name' => 'Trainer Pro',
                'description' => 'Fuer Solo-Trainer, Camps, private Gruppen und spaeter Kursverkauf.',
                'monthly_price_cents' => 1200,
                'yearly_price_cents' => 12000,
                'member_limit' => 50,
                'team_limit' => 5,
                'storage_gb' => 5,
                'features' => ['Eigene Trainingsgruppen', 'Camps und Kurse vorbereitet', 'Teilnehmerverwaltung', 'Trainingsplan-Vorlagen vorbereitet', 'Zahlungslinks vorbereitet'],
                'cta_label' => 'Trainer Pro testen',
                'badge' => 'Solo-Trainer',
                'sort_order' => 220,
            ],
            [
                'slug' => 'eltern-free',
                'target_actor' => 'eltern',
                'name' => 'Elternbereich',
                'description' => 'Kostenloser Sicherheits- und Zustimmungsbereich für Erziehungsberechtigte.',
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 1,
                'features' => ['Zustimmen oder ablehnen', 'Widerruf', 'Kinderueberblick', 'Login per E-Mail-Code', 'Optionales Elternkonto'],
                'cta_label' => 'Elternbereich nutzen',
                'badge' => 'Kostenlos',
                'sort_order' => 410,
            ],
            [
                'slug' => 'sponsor-local',
                'target_actor' => 'sponsor',
                'name' => 'Sponsor Local',
                'description' => 'Fuer regionale Sichtbarkeit bei Vereinen, Teams und Sport-Communities.',
                'monthly_price_cents' => 1900,
                'yearly_price_cents' => 19000,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 2,
                'features' => ['Sponsorprofil vorbereitet', 'Vereinsplatzierungen', 'Angebote und Kampagnen vorbereitet', 'Klick- und View-Reporting vorbereitet'],
                'cta_label' => 'Sponsoring anfragen',
                'badge' => 'B2B',
                'sort_order' => 510,
            ],
            [
                'slug' => 'anbieter-marketplace',
                'target_actor' => 'anbieter',
                'name' => 'Anbieter Marketplace',
                'description' => 'Fuer Produkte, Kurse, Camps und Dienstleistungen rund um Sport.',
                'monthly_price_cents' => 1900,
                'yearly_price_cents' => 19000,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 5,
                'features' => ['Anbieterprofil vorbereitet', 'Produkt- und Kursverkauf vorbereitet', 'Provisionen vorbereitet', 'Premium-Platzierungen vorbereitet'],
                'cta_label' => 'Anbieter werden',
                'badge' => 'Marketplace',
                'sort_order' => 610,
            ],
            [
                'slug' => 'enterprise-verband',
                'target_actor' => 'enterprise',
                'name' => 'Enterprise / Verband',
                'description' => 'Individuelle Loesung für Verbaende, Kommunen und grosse Sportnetzwerke.',
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 100,
                'features' => ['Mandantenfaehigkeit vorbereitet', 'Datenmigration', 'Schnittstellen', 'Schulung', 'AVV und SLA Paket'],
                'cta_label' => 'Kontakt aufnehmen',
                'badge' => 'Auf Anfrage',
                'sort_order' => 710,
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                [
                    'target_actor' => $plan['target_actor'],
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'monthly_price_cents' => $plan['monthly_price_cents'],
                    'yearly_price_cents' => $plan['yearly_price_cents'],
                    'currency' => 'EUR',
                    'member_limit' => $plan['member_limit'],
                    'team_limit' => $plan['team_limit'],
                    'storage_gb' => $plan['storage_gb'],
                    'features' => json_encode($plan['features']),
                    'cta_label' => $plan['cta_label'],
                    'badge' => $plan['badge'],
                    'sort_order' => $plan['sort_order'],
                    'is_public' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');

        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'badge')) {
                $table->dropColumn('badge');
            }

            if (Schema::hasColumn('subscription_plans', 'cta_label')) {
                $table->dropColumn('cta_label');
            }

            if (Schema::hasColumn('subscription_plans', 'target_actor')) {
                $table->dropIndex(['target_actor']);
                $table->dropColumn('target_actor');
            }
        });
    }
};
