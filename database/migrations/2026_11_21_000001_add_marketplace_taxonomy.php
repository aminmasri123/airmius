<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->string('catalog_group', 40)->nullable();
            $table->string('catalog_subcategory', 40)->nullable();
            $table->json('sports')->nullable();
            $table->index(['catalog_group', 'catalog_subcategory'], 'marketplace_products_catalog_index');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropIndex('marketplace_products_catalog_index');
            $table->dropColumn(['catalog_group', 'catalog_subcategory', 'sports']);
        });
    }
};
