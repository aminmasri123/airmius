<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_maintenance_records', function (Blueprint $table): void {
            $table->string('severity')->nullable()->after('status');
            $table->string('booking_impact')->default('none')->after('severity');
            $table->unsignedInteger('estimated_cost_cents')->nullable()->after('cost');
            $table->json('protected_photo_manifest')->nullable()->after('resource_lock_snapshot');
            $table->timestamp('damage_reported_at')->nullable()->after('opened_at');
            $table->index(['club_id', 'type', 'status'], 'club_inv_maint_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_maintenance_records', function (Blueprint $table): void {
            $table->dropIndex('club_inv_maint_type_status_idx');
            $table->dropColumn([
                'severity',
                'booking_impact',
                'estimated_cost_cents',
                'protected_photo_manifest',
                'damage_reported_at',
            ]);
        });
    }
};
