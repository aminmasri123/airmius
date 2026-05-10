<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_shipping_addresses')) {
            return;
        }

        Schema::create('commerce_shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('country', 2)->default('DE');
            $table->string('state', 80)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('street', 180)->nullable();
            $table->string('house_number', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_shipping_addresses');
    }
};
