<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('club_tasks', 'assignment_status')) {
                $table->json('assignment_status')->nullable()->after('assigned_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('club_tasks', 'assignment_status')) {
                $table->dropColumn('assignment_status');
            }
        });
    }
};
