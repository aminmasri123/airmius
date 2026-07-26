<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_membership_requests', 'preview_base_amount')) {
                $table->decimal('preview_base_amount', 10, 2)->nullable()->after('preview_amount');
            }
            if (! Schema::hasColumn('club_membership_requests', 'preview_discount_amount')) {
                $table->decimal('preview_discount_amount', 10, 2)->nullable()->after('preview_base_amount');
            }
            if (! Schema::hasColumn('club_membership_requests', 'preview_rule_type')) {
                $table->string('preview_rule_type', 80)->nullable()->after('preview_discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            foreach (['preview_rule_type', 'preview_discount_amount', 'preview_base_amount'] as $column) {
                if (Schema::hasColumn('club_membership_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
