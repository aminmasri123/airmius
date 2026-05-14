<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_creatives', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_creatives', 'ad_group_id')) {
                $table->foreignId('ad_group_id')
                    ->nullable()
                    ->after('ad_campaign_id')
                    ->constrained('ad_groups')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ad_creatives', 'ad_name')) {
                $table->string('ad_name')->nullable()->after('ad_group_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_creatives', function (Blueprint $table) {
            if (Schema::hasColumn('ad_creatives', 'ad_group_id')) {
                $table->dropConstrainedForeignId('ad_group_id');
            }

            if (Schema::hasColumn('ad_creatives', 'ad_name')) {
                $table->dropColumn('ad_name');
            }
        });
    }
};
