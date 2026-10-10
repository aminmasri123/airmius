<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('club_tasks', 'assignment_mode')) {
                $table->string('assignment_mode')->nullable()->after('assignment_status');
            }
            if (! Schema::hasColumn('club_tasks', 'participant_progress')) {
                $table->json('participant_progress')->nullable()->after('participant_ids');
            }
            if (! Schema::hasColumn('club_tasks', 'activity_log')) {
                $table->json('activity_log')->nullable()->after('attachment_links');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_tasks', function (Blueprint $table) {
            foreach (['assignment_mode', 'participant_progress', 'activity_log'] as $column) {
                if (Schema::hasColumn('club_tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
