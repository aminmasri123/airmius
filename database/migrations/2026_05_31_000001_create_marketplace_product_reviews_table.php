<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 30)->default('published');
            $table->boolean('verified_purchase')->default(false);
            $table->timestamps();

            $table->unique(['marketplace_product_id', 'user_id'], 'mp_reviews_product_user_unique');
            $table->index(['marketplace_product_id', 'status'], 'mp_reviews_product_status_idx');
            $table->index(['user_id', 'created_at'], 'mp_reviews_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_reviews');
    }
};
