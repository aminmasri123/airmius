<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->index(['verification_status', 'verification_requested_at'], 'clubs_verification_queue_idx');
        });
        Schema::table('user_role_applications', function (Blueprint $table): void {
            $table->index(['type', 'status', 'requested_at'], 'role_applications_queue_idx');
        });
        Schema::table('moderation_flags', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'moderation_flags_queue_idx');
        });
        Schema::table('content_reports', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'content_reports_queue_idx');
            $table->index(['appeal_status', 'appealed_at'], 'content_reports_appeal_queue_idx');
        });
        Schema::table('commerce_orders', function (Blueprint $table): void {
            $table->index(['issue_status', 'issue_reported_at'], 'commerce_orders_issue_queue_idx');
        });
        Schema::table('marketplace_payouts', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'marketplace_payouts_queue_idx');
            $table->index(['reconciliation_status', 'created_at'], 'marketplace_payouts_recovery_queue_idx');
        });
        Schema::table('outfit_deliveries', function (Blueprint $table): void {
            $table->index(['issue_status', 'issue_requested_at'], 'outfit_deliveries_issue_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outfit_deliveries', fn (Blueprint $table) => $table->dropIndex('outfit_deliveries_issue_queue_idx'));
        Schema::table('marketplace_payouts', function (Blueprint $table): void {
            $table->dropIndex('marketplace_payouts_recovery_queue_idx');
            $table->dropIndex('marketplace_payouts_queue_idx');
        });
        Schema::table('commerce_orders', fn (Blueprint $table) => $table->dropIndex('commerce_orders_issue_queue_idx'));
        Schema::table('content_reports', function (Blueprint $table): void {
            $table->dropIndex('content_reports_appeal_queue_idx');
            $table->dropIndex('content_reports_queue_idx');
        });
        Schema::table('moderation_flags', fn (Blueprint $table) => $table->dropIndex('moderation_flags_queue_idx'));
        Schema::table('user_role_applications', fn (Blueprint $table) => $table->dropIndex('role_applications_queue_idx'));
        Schema::table('clubs', fn (Blueprint $table) => $table->dropIndex('clubs_verification_queue_idx'));
    }
};
