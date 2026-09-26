<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('confidential_case_group', 80)->nullable()->after('safety_report_type');
            $table->foreignId('responsible_user_id')->nullable()->after('confidential_case_group')->constrained('users')->nullOnDelete();
            $table->json('conflict_user_ids')->nullable()->after('responsible_user_id');
            $table->string('protective_action_summary', 240)->nullable()->after('conflict_user_ids');
            $table->index(['confidential_case_group', 'status'], 'support_tickets_confidential_case_index');
        });

        Schema::create('support_ticket_confidential_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->json('metadata')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('event_hash', 64);
            $table->timestamps();

            $table->unique(['support_ticket_id', 'event_hash'], 'support_ticket_confidential_audit_hash_unique');
            $table->index(['support_ticket_id', 'id'], 'support_ticket_confidential_audit_chain_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_confidential_audits');

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_confidential_case_index');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn([
                'confidential_case_group',
                'conflict_user_ids',
                'protective_action_summary',
            ]);
        });
    }
};
