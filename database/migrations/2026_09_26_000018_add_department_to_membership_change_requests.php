<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_membership_requests', 'club_department_id')) {
                $table->foreignId('club_department_id')
                    ->nullable()
                    ->after('club_membership_type_id')
                    ->constrained('club_departments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('club_membership_requests', 'club_department_id')) {
                $table->dropConstrainedForeignId('club_department_id');
            }
        });
    }
};
