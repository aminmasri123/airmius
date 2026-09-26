<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_bulk_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->dateTime('order_window_starts_at');
            $table->dateTime('order_deadline_at');
            $table->string('status')->default('open');
            $table->string('supplier_name')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->integer('unit_price_cents');
            $table->integer('funded_share_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->json('price_snapshot');
            $table->timestamps();

            $table->index(['club_id', 'team_id', 'order_deadline_at'], 'team_bulk_orders_scope_deadline_idx');
        });

        Schema::create('team_bulk_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_bulk_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('personalization')->nullable();
            $table->integer('unit_price_cents');
            $table->integer('funded_share_cents')->default(0);
            $table->integer('payable_unit_price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->unique(['team_bulk_order_id', 'user_id'], 'team_bulk_order_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_bulk_order_items');
        Schema::dropIfExists('team_bulk_orders');
    }
};
