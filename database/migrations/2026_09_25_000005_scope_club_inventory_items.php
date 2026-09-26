<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->foreignId('club_department_id')
                ->nullable()
                ->after('club_id')
                ->constrained('club_departments')
                ->restrictOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->after('club_department_id')
                ->constrained('teams')
                ->restrictOnDelete();
            $table->index(['club_id', 'club_department_id']);
            $table->index(['club_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['club_department_id']);
            $table->dropIndex(['club_id', 'team_id']);
            $table->dropIndex(['club_id', 'club_department_id']);
            $table->dropColumn(['club_department_id', 'team_id']);
        });
    }
};
