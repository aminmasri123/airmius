<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_deliveries', 'issue_type')) {
                $table->string('issue_type')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_status')) {
                $table->string('issue_status')->nullable()->after('issue_type');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_description')) {
                $table->text('issue_description')->nullable()->after('issue_status');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_requested_resolution')) {
                $table->text('issue_requested_resolution')->nullable()->after('issue_description');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_exchange_size')) {
                $table->string('issue_exchange_size', 60)->nullable()->after('issue_requested_resolution');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_admin_note')) {
                $table->text('issue_admin_note')->nullable()->after('issue_exchange_size');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'return_tracking_number')) {
                $table->string('return_tracking_number')->nullable()->after('issue_admin_note');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'return_tracking_url')) {
                $table->text('return_tracking_url')->nullable()->after('return_tracking_number');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_requested_at')) {
                $table->timestamp('issue_requested_at')->nullable()->after('return_tracking_url');
            }

            if (! Schema::hasColumn('outfit_deliveries', 'issue_resolved_at')) {
                $table->timestamp('issue_resolved_at')->nullable()->after('issue_requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_deliveries', function (Blueprint $table) {
            foreach ([
                'issue_resolved_at',
                'issue_requested_at',
                'return_tracking_url',
                'return_tracking_number',
                'issue_admin_note',
                'issue_exchange_size',
                'issue_requested_resolution',
                'issue_description',
                'issue_status',
                'issue_type',
            ] as $column) {
                if (Schema::hasColumn('outfit_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
