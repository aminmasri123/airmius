<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_price_lists')) {
            Schema::create('commerce_price_lists', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'commerce_price_lists_club_fk')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'commerce_price_lists_creator_fk')->nullOnDelete();
                $table->string('name');
                $table->string('version', 40);
                $table->string('status', 30)->default('draft');
                $table->timestamp('valid_from');
                $table->timestamp('valid_until')->nullable();
                $table->timestamps();

                $table->unique(['club_id', 'version'], 'commerce_price_lists_club_version_unique');
                $table->index(['club_id', 'status', 'valid_from', 'valid_until'], 'commerce_price_lists_status_validity_idx');
            });
        }

        if (! Schema::hasTable('commerce_price_list_items')) {
            Schema::create('commerce_price_list_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commerce_price_list_id')->constrained(indexName: 'commerce_price_items_list_fk')->cascadeOnDelete();
                $table->foreignId('marketplace_product_id')->constrained(indexName: 'commerce_price_items_product_fk')->cascadeOnDelete();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 3)->default('EUR');
                $table->unsignedInteger('min_quantity')->default(1);
                $table->unsignedInteger('max_quantity')->nullable();
                $table->timestamps();

                $table->unique(['commerce_price_list_id', 'marketplace_product_id', 'min_quantity'], 'commerce_price_items_list_product_qty_unique');
            });
        }

        if (! Schema::hasTable('event_commerce_assortments')) {
            Schema::create('event_commerce_assortments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained(indexName: 'event_commerce_assortments_event_fk')->cascadeOnDelete();
                $table->foreignId('commerce_price_list_id')->constrained(indexName: 'event_commerce_assortments_list_fk')->cascadeOnDelete();
                $table->foreignId('marketplace_product_id')->constrained(indexName: 'event_commerce_assortments_product_fk')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();

                $table->unique(['event_id', 'marketplace_product_id'], 'event_commerce_assort_event_product_unique');
                $table->index(['commerce_price_list_id', 'sort_order'], 'event_commerce_assort_list_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_commerce_assortments');
        Schema::dropIfExists('commerce_price_list_items');
        Schema::dropIfExists('commerce_price_lists');
    }
};
