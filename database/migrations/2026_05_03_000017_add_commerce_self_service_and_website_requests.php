<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_orders', 'confirmation_email_sent_at')) {
                $table->timestamp('confirmation_email_sent_at')->nullable()->after('completed_at');
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_products', 'payout_status')) {
                $table->string('payout_status', 30)->default('not_applicable')->after('commission_percent');
            }
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_campaigns', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        Schema::create('website_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('new');
            $table->string('package', 40)->default('website_plus');
            $table->string('domain')->nullable();
            $table->text('goals')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        if (! DB::table('subscription_addons')->where('slug', 'club-website-build')->exists()) {
            DB::table('subscription_addons')->insert([
                'slug' => 'club-website-build',
                'name' => 'Vereinswebsite erstellen lassen',
                'description' => 'Airmius erstellt fuer deinen Verein eine moderne Website auf Basis eurer Vereinsdaten.',
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'target_actor' => 'verein',
                'features' => json_encode(['Design und Struktur', 'Vereinsprofil und Teams', 'SEO-Grundlage', 'Angebot nach Aufwand']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_requests');

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_products', 'payout_status')) {
                $table->dropColumn('payout_status');
            }
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('ad_campaigns', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('commerce_orders', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_orders', 'confirmation_email_sent_at')) {
                $table->dropColumn('confirmation_email_sent_at');
            }
        });
    }
};
