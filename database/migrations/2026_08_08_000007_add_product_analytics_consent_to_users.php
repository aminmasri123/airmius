<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'product_analytics_consent')) {
                $table->boolean('product_analytics_consent')->default(false)->after('ads_measurement_consent');
            }

            if (! Schema::hasColumn('users', 'product_analytics_consented_at')) {
                $table->timestamp('product_analytics_consented_at')->nullable()->after('product_analytics_consent');
            }

            if (! Schema::hasColumn('users', 'product_analytics_consent_version')) {
                $table->string('product_analytics_consent_version', 80)->nullable()->after('product_analytics_consented_at');
            }
        });

        if (! Schema::hasIndex('users', 'users_product_analytics_cohort_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(
                    ['product_analytics_consent', 'birth_date', 'last_seen_at'],
                    'users_product_analytics_cohort_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('users', 'users_product_analytics_cohort_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_product_analytics_cohort_index');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'product_analytics_consent_version',
                'product_analytics_consented_at',
                'product_analytics_consent',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
