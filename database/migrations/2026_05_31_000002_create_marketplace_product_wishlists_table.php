<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'marketplace_product_id'], 'mp_wishlists_user_product_unique');
            $table->index(['marketplace_product_id', 'created_at'], 'mp_wishlists_product_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_wishlists');
    }
};
