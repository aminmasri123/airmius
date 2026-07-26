<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('support_tickets', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('last_reply_at');
            }

            if (! Schema::hasColumn('support_tickets', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('due_at');
            }

            if (! Schema::hasColumn('support_tickets', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('escalated_at');
            }

            if (! Schema::hasColumn('support_tickets', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('resolved_at');
            }
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->index(['status', 'due_at'], 'support_tickets_sla_index');
            $table->index(['assigned_to', 'status'], 'support_tickets_assignee_index');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_sla_index');
            $table->dropIndex('support_tickets_assignee_index');

            foreach (['admin_note', 'resolved_at', 'escalated_at', 'due_at'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }
        });
    }
};
