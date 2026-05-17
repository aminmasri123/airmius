<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ads_personalization_consent')) {
                $table->boolean('ads_personalization_consent')->default(false)->after('friend_request_privacy');
            }

            if (! Schema::hasColumn('users', 'ads_measurement_consent')) {
                $table->boolean('ads_measurement_consent')->default(false)->after('ads_personalization_consent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['ads_measurement_consent', 'ads_personalization_consent'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
