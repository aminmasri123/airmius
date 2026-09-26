<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->unsignedInteger('minimum_stock')->default(0)->after('quantity_available');
            $table->unsignedInteger('reserved_quantity')->default(0)->after('minimum_stock');
            $table->unsignedSmallInteger('reorder_lead_time_days')->default(0)->after('reserved_quantity');
            $table->index(['club_id', 'minimum_stock']);
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropIndex(['club_id', 'minimum_stock']);
            $table->dropColumn(['minimum_stock', 'reserved_quantity', 'reorder_lead_time_days']);
        });
    }
};
