<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_maintenance_records', function (Blueprint $table): void {
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->after('resolved_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->after('responsible_user_id')
                ->constrained('club_inventory_maintenance_records')
                ->nullOnDelete();
            $table->string('type', 32)->default('maintenance')->after('parent_id');
            $table->timestamp('starts_at')->nullable()->after('opened_at');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->string('recurrence_frequency', 32)->nullable()->after('ends_at');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
            $table->date('recurrence_until')->nullable()->after('recurrence_interval');
            $table->boolean('blocks_resource')->default(false)->after('recurrence_until');
            $table->json('resource_lock_snapshot')->nullable()->after('blocks_resource');

            $table->index(['club_id', 'responsible_user_id', 'status'], 'club_inv_maint_resp_status_index');
            $table->index(['club_inventory_item_id', 'status', 'starts_at', 'ends_at'], 'club_inv_maint_window_index');
            $table->index(['club_id', 'type', 'status'], 'club_inv_maint_type_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_maintenance_records', function (Blueprint $table): void {
            $table->dropIndex('club_inv_maint_resp_status_index');
            $table->dropIndex('club_inv_maint_window_index');
            $table->dropIndex('club_inv_maint_type_status_index');
            $table->dropForeign(['responsible_user_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'responsible_user_id',
                'parent_id',
                'type',
                'starts_at',
                'ends_at',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_until',
                'blocks_resource',
                'resource_lock_snapshot',
            ]);
        });
    }
};
