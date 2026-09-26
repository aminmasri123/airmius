<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->json('opening_hours')->nullable()->after('resource_type');
            $table->json('booking_rules')->nullable()->after('opening_hours');
            $table->timestamp('qr_issued_at')->nullable()->after('qr_token');
            $table->timestamp('qr_revoked_at')->nullable()->after('qr_issued_at');
            $table->index(['club_id', 'qr_revoked_at']);
        });

        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('booking_priority')->default(100)->after('quantity');
            $table->index(['club_inventory_item_id', 'status', 'booking_priority'], 'club_inventory_loans_resource_priority_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropIndex('club_inventory_loans_resource_priority_index');
            $table->dropColumn('booking_priority');
        });

        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropIndex(['club_id', 'qr_revoked_at']);
            $table->dropColumn(['opening_hours', 'booking_rules', 'qr_issued_at', 'qr_revoked_at']);
        });
    }
};
