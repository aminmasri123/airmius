<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->string('article_number', 120)->nullable()->after('sku');
            $table->string('batch_number', 120)->nullable()->after('article_number');
            $table->unsignedInteger('purchase_price_cents')->nullable()->after('batch_number');
            $table->unsignedInteger('deposit_cents')->default(0)->after('purchase_price_cents');
            $table->string('supplier', 180)->nullable()->after('deposit_cents');
            $table->date('purchased_on')->nullable()->after('supplier');
        });

        Schema::create('club_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('club_inventory_item_id')->constrained('club_inventory_items')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->unsignedInteger('purchase_price_cents')->nullable();
            $table->unsignedInteger('deposit_cents')->nullable();
            $table->string('batch_number', 120)->nullable();
            $table->string('supplier', 180)->nullable();
            $table->date('occurred_on')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('correction_of_id')->nullable()->constrained('club_inventory_movements')->nullOnDelete();
            $table->json('correction_snapshot')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'type', 'occurred_on']);
            $table->index(['club_inventory_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_inventory_movements');

        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropColumn([
                'article_number',
                'batch_number',
                'purchase_price_cents',
                'deposit_cents',
                'supplier',
                'purchased_on',
            ]);
        });
    }
};
