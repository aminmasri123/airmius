<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_products', 'product_type')) {
                $table->string('product_type', 30)->default('single')->after('category');
            }

            if (! Schema::hasColumn('marketplace_products', 'attribute_options')) {
                $table->json('attribute_options')->nullable()->after('product_attributes');
            }

            if (! Schema::hasColumn('marketplace_products', 'variants')) {
                $table->json('variants')->nullable()->after('attribute_options');
            }

            if (! Schema::hasColumn('marketplace_products', 'digital_delivery_note')) {
                $table->text('digital_delivery_note')->nullable()->after('return_window_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            foreach (['digital_delivery_note', 'variants', 'attribute_options', 'product_type'] as $column) {
                if (Schema::hasColumn('marketplace_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
