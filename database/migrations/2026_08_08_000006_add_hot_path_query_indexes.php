<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'notifications_user_created_idx');
            $table->index(['user_id', 'read', 'type', 'created_at'], 'notifications_user_unread_type_created_idx');
        });

        Schema::table('conversation_users', function (Blueprint $table): void {
            $table->index(['user_id', 'conversation_id'], 'conversation_users_user_conversation_idx');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->index(['start_time', 'status'], 'events_start_status_idx');
            $table->index(['team_id', 'start_time'], 'events_team_start_idx');
            $table->index(['club_id', 'start_time'], 'events_club_start_idx');
            $table->index(
                ['status', 'reminder_sent_at', 'reminder_at', 'start_time'],
                'events_reminder_due_idx',
            );
        });

        Schema::table('mobile_push_deliveries', function (Blueprint $table): void {
            $table->index(
                ['status', 'next_attempt_at', 'queued_at'],
                'mobile_push_status_retry_queued_idx',
            );
        });

        Schema::table('domain_outbox_events', function (Blueprint $table): void {
            $table->index(
                ['published_at', 'available_at', 'processing_at', 'occurred_at'],
                'domain_outbox_dispatch_idx',
            );
        });
        Schema::table('domain_outbox_events', function (Blueprint $table): void {
            $table->dropIndex('domain_outbox_pending_index');
        });
    }

    public function down(): void
    {
        Schema::table('domain_outbox_events', function (Blueprint $table): void {
            $table->index(['published_at', 'available_at'], 'domain_outbox_pending_index');
        });
        Schema::table('domain_outbox_events', function (Blueprint $table): void {
            $table->dropIndex('domain_outbox_dispatch_idx');
        });

        Schema::table('mobile_push_deliveries', function (Blueprint $table): void {
            $table->dropIndex('mobile_push_status_retry_queued_idx');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_reminder_due_idx');
            $table->dropIndex('events_club_start_idx');
            $table->dropIndex('events_team_start_idx');
            $table->dropIndex('events_start_status_idx');
        });

        Schema::table('conversation_users', function (Blueprint $table): void {
            $table->dropIndex('conversation_users_user_conversation_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_user_unread_type_created_idx');
            $table->dropIndex('notifications_user_created_idx');
        });
    }
};
