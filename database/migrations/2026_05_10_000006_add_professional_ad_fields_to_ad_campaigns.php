<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_campaigns', 'headline')) {
                $table->string('headline')->nullable()->after('name');
            }

            if (! Schema::hasColumn('ad_campaigns', 'primary_text')) {
                $table->text('primary_text')->nullable()->after('description');
            }

            if (! Schema::hasColumn('ad_campaigns', 'cta_label')) {
                $table->string('cta_label', 80)->nullable()->after('target_url');
            }

            if (! Schema::hasColumn('ad_campaigns', 'objective')) {
                $table->string('objective', 40)->default('traffic')->after('cta_label');
            }

            if (! Schema::hasColumn('ad_campaigns', 'placement')) {
                $table->string('placement', 40)->default('marketplace_card')->after('objective');
            }

            if (! Schema::hasColumn('ad_campaigns', 'creative_format')) {
                $table->string('creative_format', 40)->default('feed_square')->after('placement');
            }

            if (! Schema::hasColumn('ad_campaigns', 'creative_image_path')) {
                $table->string('creative_image_path')->nullable()->after('creative_format');
            }

            if (! Schema::hasColumn('ad_campaigns', 'creative_image_url')) {
                $table->string('creative_image_url')->nullable()->after('creative_image_path');
            }

            if (! Schema::hasColumn('ad_campaigns', 'audience')) {
                $table->json('audience')->nullable()->after('creative_image_url');
            }

            if (! Schema::hasColumn('ad_campaigns', 'daily_budget_cents')) {
                $table->unsignedInteger('daily_budget_cents')->default(0)->after('budget_cents');
            }

            if (! Schema::hasColumn('ad_campaigns', 'billing_event')) {
                $table->string('billing_event', 40)->default('impression')->after('daily_budget_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            foreach ([
                'headline',
                'primary_text',
                'cta_label',
                'objective',
                'placement',
                'creative_format',
                'creative_image_path',
                'creative_image_url',
                'audience',
                'daily_budget_cents',
                'billing_event',
            ] as $column) {
                if (Schema::hasColumn('ad_campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
