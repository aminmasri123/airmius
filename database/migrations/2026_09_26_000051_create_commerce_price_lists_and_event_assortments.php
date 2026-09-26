<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('version', 40);
            $table->string('status', 30)->default('draft');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'version']);
            $table->index(['club_id', 'status', 'valid_from', 'valid_until']);
        });

        Schema::create('commerce_price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commerce_price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->timestamps();

            $table->unique(['commerce_price_list_id', 'marketplace_product_id', 'min_quantity']);
        });

        Schema::create('event_commerce_assortments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'marketplace_product_id']);
            $table->index(['commerce_price_list_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_commerce_assortments');
        Schema::dropIfExists('commerce_price_list_items');
        Schema::dropIfExists('commerce_price_lists');
    }
};
