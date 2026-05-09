<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscription_plans', 'paypal_product_id')) {
                $table->string('paypal_product_id')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'paypal_plan_id')) {
                $table->string('paypal_plan_id')->nullable()->after('paypal_product_id');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'paypal_plan_signature')) {
                $table->string('paypal_plan_signature')->nullable()->after('paypal_plan_id');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'paypal_payload')) {
                $table->json('paypal_payload')->nullable()->after('paypal_plan_signature');
            }
        });

        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'provider_subscription_id')) {
                $table->string('provider_subscription_id')->nullable()->after('provider_checkout_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('outfit_subscriptions', 'provider_subscription_id')) {
                $table->dropColumn('provider_subscription_id');
            }
        });

        Schema::table('outfit_subscription_plans', function (Blueprint $table) {
            foreach (['paypal_payload', 'paypal_plan_signature', 'paypal_plan_id', 'paypal_product_id'] as $column) {
                if (Schema::hasColumn('outfit_subscription_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
