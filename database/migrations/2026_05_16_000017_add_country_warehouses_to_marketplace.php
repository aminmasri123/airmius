<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_warehouses')) {
            Schema::create('commerce_warehouses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('country_code', 2)->index();
                $table->string('city')->nullable();
                $table->string('postal_code', 30)->nullable();
                $table->string('address_line')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'country_code', 'sort_order'], 'commerce_warehouses_active_country_idx');
            });
        }

        if (! Schema::hasTable('marketplace_product_inventories')) {
            Schema::create('marketplace_product_inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('commerce_warehouse_id')->constrained()->cascadeOnDelete();
                $table->string('country_code', 2)->index();
                $table->unsignedInteger('stock_quantity')->default(0);
                $table->unsignedInteger('reserved_quantity')->default(0);
                $table->unsignedInteger('low_stock_threshold')->default(0);
                $table->unsignedInteger('lead_time_days')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['marketplace_product_id', 'commerce_warehouse_id', 'country_code'], 'mpi_product_warehouse_country_unique');
                $table->index(['marketplace_product_id', 'country_code', 'is_active'], 'mpi_product_country_active_idx');
            });
        }

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_products', 'available_countries')) {
                $table->json('available_countries')->nullable()->after('currency');
            }
        });

        Schema::table('commerce_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_order_items', 'commerce_warehouse_id')) {
                $table->foreignId('commerce_warehouse_id')->nullable()->after('commerce_order_id')->constrained('commerce_warehouses')->nullOnDelete();
            }
        });

        Schema::table('commerce_stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_stock_movements', 'commerce_warehouse_id')) {
                $table->foreignId('commerce_warehouse_id')->nullable()->after('marketplace_product_id')->constrained('commerce_warehouses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_stock_movements', 'commerce_warehouse_id')) {
                $table->dropConstrainedForeignId('commerce_warehouse_id');
            }
        });

        Schema::table('commerce_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_order_items', 'commerce_warehouse_id')) {
                $table->dropConstrainedForeignId('commerce_warehouse_id');
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_products', 'available_countries')) {
                $table->dropColumn('available_countries');
            }
        });

        Schema::dropIfExists('marketplace_product_inventories');
        Schema::dropIfExists('commerce_warehouses');
    }
};
