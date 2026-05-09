<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscription_plans', 'contract_title')) {
                $table->string('contract_title')->nullable()->after('description');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'contract_terms')) {
                $table->json('contract_terms')->nullable()->after('contract_title');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'minimum_term_months')) {
                $table->unsignedTinyInteger('minimum_term_months')->nullable()->after('contract_terms');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'pause_allowed_after_months')) {
                $table->unsignedTinyInteger('pause_allowed_after_months')->nullable()->after('minimum_term_months');
            }

            if (! Schema::hasColumn('outfit_subscription_plans', 'cancellation_notice_days')) {
                $table->unsignedTinyInteger('cancellation_notice_days')->nullable()->after('pause_allowed_after_months');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscription_plans', function (Blueprint $table) {
            foreach ([
                'cancellation_notice_days',
                'pause_allowed_after_months',
                'minimum_term_months',
                'contract_terms',
                'contract_title',
            ] as $column) {
                if (Schema::hasColumn('outfit_subscription_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
