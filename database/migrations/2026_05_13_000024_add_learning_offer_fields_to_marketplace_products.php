<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_products', 'offer_type')) {
                $table->string('offer_type', 40)->default('physical_product')->after('category')->index();
            }

            if (! Schema::hasColumn('marketplace_products', 'course_outline')) {
                $table->json('course_outline')->nullable()->after('digital_delivery_note');
            }

            if (! Schema::hasColumn('marketplace_products', 'learning_goals')) {
                $table->json('learning_goals')->nullable()->after('course_outline');
            }

            if (! Schema::hasColumn('marketplace_products', 'coaching_enabled')) {
                $table->boolean('coaching_enabled')->default(false)->after('learning_goals');
            }

            if (! Schema::hasColumn('marketplace_products', 'coach_feedback_instructions')) {
                $table->text('coach_feedback_instructions')->nullable()->after('coaching_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            foreach (['coach_feedback_instructions', 'coaching_enabled', 'learning_goals', 'course_outline', 'offer_type'] as $column) {
                if (Schema::hasColumn('marketplace_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
