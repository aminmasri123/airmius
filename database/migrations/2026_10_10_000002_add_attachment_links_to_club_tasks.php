<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_tasks', 'attachment_links')) {
                $table->json('attachment_links')->nullable()->after('checklist');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('club_tasks', 'attachment_links')) {
                $table->dropColumn('attachment_links');
            }
        });
    }
};
