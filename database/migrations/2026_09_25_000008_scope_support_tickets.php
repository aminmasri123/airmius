<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('club_department_id')
                ->nullable()
                ->after('club_id')
                ->constrained('club_departments')
                ->nullOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->after('club_department_id')
                ->constrained('teams')
                ->nullOnDelete();
            $table->index(['club_id', 'club_department_id', 'status'], 'support_tickets_department_scope_index');
            $table->index(['club_id', 'team_id', 'status'], 'support_tickets_team_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_department_scope_index');
            $table->dropIndex('support_tickets_team_scope_index');
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('club_department_id');
        });
    }
};
