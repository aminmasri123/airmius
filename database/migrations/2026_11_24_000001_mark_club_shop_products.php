<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->boolean('is_club_shop_product')->default(false);
            $table->index(['club_id', 'is_club_shop_product']);
        });
        Schema::table('commerce_order_items', function (Blueprint $table) {
            $table->foreignId('shop_club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('club_fulfillment_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commerce_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_club_id');
            $table->dropColumn('club_fulfillment_status');
        });
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'is_club_shop_product']);
            $table->dropColumn('is_club_shop_product');
        });
    }
};
