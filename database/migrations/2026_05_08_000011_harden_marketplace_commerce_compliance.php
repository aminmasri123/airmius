<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_tax_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_tax_rates', 'tax_class')) {
                $table->string('tax_class', 30)->default('standard')->after('region');
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_products', 'sku')) {
                $table->string('sku', 80)->nullable()->after('category');
            }
            if (! Schema::hasColumn('marketplace_products', 'is_shippable')) {
                $table->boolean('is_shippable')->default(true)->after('sku');
            }
            if (! Schema::hasColumn('marketplace_products', 'manages_stock')) {
                $table->boolean('manages_stock')->default(false)->after('is_shippable');
            }
            if (! Schema::hasColumn('marketplace_products', 'stock_quantity')) {
                $table->integer('stock_quantity')->nullable()->after('manages_stock');
            }
            if (! Schema::hasColumn('marketplace_products', 'tax_class')) {
                $table->string('tax_class', 30)->default('standard')->after('stock_quantity');
            }
        });

        Schema::table('commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_orders', 'item_gross_cents')) {
                $table->unsignedInteger('item_gross_cents')->default(0)->after('billing_interval');
            }
            if (! Schema::hasColumn('commerce_orders', 'shipping_cents')) {
                $table->unsignedInteger('shipping_cents')->default(0)->after('item_gross_cents');
            }
            if (! Schema::hasColumn('commerce_orders', 'net_cents')) {
                $table->unsignedInteger('net_cents')->default(0)->after('shipping_cents');
            }
            if (! Schema::hasColumn('commerce_orders', 'tax_cents')) {
                $table->unsignedInteger('tax_cents')->default(0)->after('net_cents');
            }
            if (! Schema::hasColumn('commerce_orders', 'tax_country')) {
                $table->string('tax_country', 2)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('commerce_orders', 'tax_rate_percent')) {
                $table->decimal('tax_rate_percent', 5, 2)->nullable()->after('tax_country');
            }
            if (! Schema::hasColumn('commerce_orders', 'customer_type')) {
                $table->string('customer_type', 20)->default('consumer')->after('access_token');
            }
            if (! Schema::hasColumn('commerce_orders', 'customer_company')) {
                $table->string('customer_company')->nullable()->after('customer_type');
            }
            if (! Schema::hasColumn('commerce_orders', 'customer_vat_id')) {
                $table->string('customer_vat_id', 40)->nullable()->after('customer_company');
            }
        });

        $settings = [
            'commerce_company_country' => 'DE',
            'commerce_company_currency' => 'EUR',
            'commerce_enable_oss' => '1',
            'commerce_export_vat_mode' => 'zero',
            'commerce_reverse_charge_enabled' => '1',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            foreach (['customer_vat_id', 'customer_company', 'customer_type', 'tax_rate_percent', 'tax_country', 'tax_cents', 'net_cents', 'shipping_cents', 'item_gross_cents'] as $column) {
                if (Schema::hasColumn('commerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            foreach (['tax_class', 'stock_quantity', 'manages_stock', 'is_shippable', 'sku'] as $column) {
                if (Schema::hasColumn('marketplace_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('commerce_tax_rates', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_tax_rates', 'tax_class')) {
                $table->dropColumn('tax_class');
            }
        });
    }
};
