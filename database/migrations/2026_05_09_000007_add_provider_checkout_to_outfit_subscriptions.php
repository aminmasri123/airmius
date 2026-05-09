<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'provider_checkout_id')) {
                $table->string('provider_checkout_id')->nullable()->after('payment_payload');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'checkout_url')) {
                $table->text('checkout_url')->nullable()->after('provider_checkout_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('outfit_subscriptions', 'checkout_url')) {
                $table->dropColumn('checkout_url');
            }

            if (Schema::hasColumn('outfit_subscriptions', 'provider_checkout_id')) {
                $table->dropColumn('provider_checkout_id');
            }
        });
    }
};
