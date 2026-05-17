<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->index(['user_id', 'folder_id']);
            $table->index(['club_id', 'team_id', 'event_id', 'folder_id']);
            $table->index('folder_id');
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->index('parent_id');
            $table->index(['user_id', 'parent_id']);
            $table->index(['club_id', 'parent_id']);
            $table->index(['team_id', 'parent_id']);
            $table->index(['event_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'folder_id']);
            $table->dropIndex(['club_id', 'team_id', 'event_id', 'folder_id']);
            $table->dropIndex(['folder_id']);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['user_id', 'parent_id']);
            $table->dropIndex(['club_id', 'parent_id']);
            $table->dropIndex(['team_id', 'parent_id']);
            $table->dropIndex(['event_id', 'parent_id']);
        });
    }
};
