<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_orders', 'issue_response')) {
                $table->text('issue_response')->nullable()->after('issue_reported_at');
            }

            if (! Schema::hasColumn('commerce_orders', 'issue_responded_at')) {
                $table->timestamp('issue_responded_at')->nullable()->after('issue_response');
            }

            if (! Schema::hasColumn('commerce_orders', 'issue_responded_by')) {
                $table->foreignId('issue_responded_by')->nullable()->after('issue_responded_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_orders', 'issue_responded_by')) {
                $table->dropConstrainedForeignId('issue_responded_by');
            }

            foreach (['issue_responded_at', 'issue_response'] as $column) {
                if (Schema::hasColumn('commerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
