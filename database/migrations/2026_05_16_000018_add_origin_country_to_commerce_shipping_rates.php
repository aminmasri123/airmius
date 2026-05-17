<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_shipping_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_shipping_rates', 'origin_country_code')) {
                $table->string('origin_country_code', 2)->nullable()->after('name')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_shipping_rates', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_shipping_rates', 'origin_country_code')) {
                $table->dropColumn('origin_country_code');
            }
        });
    }
};
