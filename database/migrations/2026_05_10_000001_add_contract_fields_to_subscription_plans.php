<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'minimum_term_months')) {
                $table->unsignedTinyInteger('minimum_term_months')->default(0)->after('features');
            }

            if (! Schema::hasColumn('subscription_plans', 'cancellation_notice_days')) {
                $table->unsignedTinyInteger('cancellation_notice_days')->default(0)->after('minimum_term_months');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            foreach (['cancellation_notice_days', 'minimum_term_months'] as $column) {
                if (Schema::hasColumn('subscription_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
