<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('team_id')
                ->constrained('club_inventory_items')
                ->restrictOnDelete();
            $table->string('resource_type', 32)->default('equipment')->after('parent_id');
            $table->index(['club_id', 'parent_id']);
            $table->index(['club_id', 'resource_type']);
        });

        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->after('checked_out_at');
            $table->index(['club_inventory_item_id', 'status', 'starts_at', 'due_at'], 'club_inventory_loans_resource_window_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropIndex('club_inventory_loans_resource_window_index');
            $table->dropColumn('starts_at');
        });

        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['club_id', 'resource_type']);
            $table->dropIndex(['club_id', 'parent_id']);
            $table->dropColumn(['parent_id', 'resource_type']);
        });
    }
};
