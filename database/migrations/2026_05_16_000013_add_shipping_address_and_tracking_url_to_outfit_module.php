<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_name')) {
                $table->string('shipping_name')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_country')) {
                $table->string('shipping_country', 2)->nullable()->after('shipping_name');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_street')) {
                $table->string('shipping_street')->nullable()->after('shipping_country');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_house_number')) {
                $table->string('shipping_house_number', 40)->nullable()->after('shipping_street');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_postal_code')) {
                $table->string('shipping_postal_code', 30)->nullable()->after('shipping_house_number');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_city')) {
                $table->string('shipping_city')->nullable()->after('shipping_postal_code');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_state')) {
                $table->string('shipping_state')->nullable()->after('shipping_city');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'shipping_note')) {
                $table->text('shipping_note')->nullable()->after('shipping_state');
            }
        });

        Schema::table('outfit_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_deliveries', 'tracking_url')) {
                $table->text('tracking_url')->nullable()->after('tracking_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('outfit_deliveries', 'tracking_url')) {
                $table->dropColumn('tracking_url');
            }
        });

        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            foreach ([
                'shipping_note',
                'shipping_state',
                'shipping_city',
                'shipping_postal_code',
                'shipping_house_number',
                'shipping_street',
                'shipping_country',
                'shipping_name',
            ] as $column) {
                if (Schema::hasColumn('outfit_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
