<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'members_can_post_to_club')) {
                $table->boolean('members_can_post_to_club')->default(true)->after('teams_are_listed');
            }

            if (! Schema::hasColumn('clubs', 'members_can_post_to_teams')) {
                $table->boolean('members_can_post_to_teams')->default(true)->after('members_can_post_to_club');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            foreach (['members_can_post_to_teams', 'members_can_post_to_club'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
