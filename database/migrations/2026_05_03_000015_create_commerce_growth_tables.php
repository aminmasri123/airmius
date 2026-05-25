<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 20)->default('percent');
            $table->unsignedInteger('value_cents')->nullable();
            $table->unsignedTinyInteger('percent_off')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedInteger('yearly_price_cents')->default(0);
            $table->string('target_actor', 30)->default('verein');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_addon_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('product');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 30)->default('draft');
            $table->unsignedTinyInteger('commission_percent')->default(10);
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('budget_cents')->default(0);
            $table->unsignedInteger('spent_cents')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_checkouts', 'subscription_coupon_id')) {
                $table->foreignId('subscription_coupon_id')->nullable()->after('subscription_plan_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_checkouts', 'original_amount_cents')) {
                $table->unsignedInteger('original_amount_cents')->nullable()->after('billing_interval');
            }

            if (! Schema::hasColumn('payment_checkouts', 'discount_cents')) {
                $table->unsignedInteger('discount_cents')->default(0)->after('original_amount_cents');
            }
        });

        DB::table('subscription_addons')->insert([
            [
                'slug' => 'storage-10gb',
                'name' => 'Speicher +10 GB',
                'description' => 'Mehr Dokumenten- und Medien-Speicher für Vereine.',
                'monthly_price_cents' => 500,
                'yearly_price_cents' => 5000,
                'target_actor' => 'verein',
                'features' => json_encode(['+10 GB Speicher', 'Ideal für Dateien, Bilder und Vereinsdokumente']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'website-plus',
                'name' => 'Website Plus',
                'description' => 'öffentliche Vereinsseite, SEO und Sponsorbereiche als Add-on.',
                'monthly_price_cents' => 900,
                'yearly_price_cents' => 9000,
                'target_actor' => 'verein',
                'features' => json_encode(['SEO-Vereinsseite', 'Sponsorbereiche', 'Events und Jobs sichtbar machen']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'finance-plus',
                'name' => 'Finanzmodul Plus',
                'description' => 'Erweiterte Finanzfunktionen für professionelle Vereinsverwaltung.',
                'monthly_price_cents' => 1900,
                'yearly_price_cents' => 19000,
                'target_actor' => 'verein',
                'features' => json_encode(['Erweiterte Mahnlaeufe', 'Finanzexports', 'Kassenwart-Auswertungen']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('payment_checkouts', function (Blueprint $table) {
            foreach (['discount_cents', 'original_amount_cents', 'subscription_coupon_id'] as $column) {
                if (Schema::hasColumn('payment_checkouts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('subscription_addon_purchases');
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('subscription_coupons');
    }
};
