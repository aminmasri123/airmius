<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_role_assignments', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('assigned_by');
            $table->date('ends_on')->nullable()->after('starts_on');

            $table->index(['club_id', 'user_id', 'starts_on', 'ends_on'], 'club_role_assignments_validity_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_role_assignments', function (Blueprint $table) {
            $table->dropIndex('club_role_assignments_validity_index');
            $table->dropColumn(['starts_on', 'ends_on']);
        });
    }
};
