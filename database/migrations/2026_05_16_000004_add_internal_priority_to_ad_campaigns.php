<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_campaigns', 'is_internal')) {
                $table->boolean('is_internal')->default(false)->after('club_id');
            }

            if (! Schema::hasColumn('ad_campaigns', 'force_priority')) {
                $table->boolean('force_priority')->default(false)->after('is_internal');
            }

            $table->index(['is_internal', 'force_priority', 'status'], 'ad_campaigns_internal_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropIndex('ad_campaigns_internal_priority_idx');

            foreach (['force_priority', 'is_internal'] as $column) {
                if (Schema::hasColumn('ad_campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
