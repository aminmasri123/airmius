<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_tasks', 'due_time')) {
                $table->time('due_time')->nullable()->after('due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('club_tasks', 'due_time')) {
                $table->dropColumn('due_time');
            }
        });
    }
};
