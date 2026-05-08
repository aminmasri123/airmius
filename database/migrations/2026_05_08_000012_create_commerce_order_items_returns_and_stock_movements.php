<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('orderable');
            $table->string('title');
            $table->string('sku', 80)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_gross_cents')->default(0);
            $table->unsignedInteger('shipping_cents')->default(0);
            $table->unsignedInteger('net_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('tax_rate_percent', 5, 2)->nullable();
            $table->string('tax_class', 30)->default('standard');
            $table->boolean('is_shippable')->default(false);
            $table->timestamps();

            $table->index(['commerce_order_id']);
        });

        Schema::create('commerce_return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->string('status', 30)->default('requested');
            $table->text('reason');
            $table->text('resolution_note')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('requested_amount_cents')->default(0);
            $table->unsignedInteger('approved_amount_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('commerce_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commerce_return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_delta');
            $table->integer('stock_after')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['marketplace_product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_stock_movements');
        Schema::dropIfExists('commerce_return_requests');
        Schema::dropIfExists('commerce_order_items');
    }
};
