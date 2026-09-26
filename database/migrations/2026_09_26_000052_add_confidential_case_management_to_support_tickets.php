<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_tickets', 'confidential_case_group')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->string('confidential_case_group', 80)->nullable();
            });
        }

        if (! Schema::hasColumn('support_tickets', 'responsible_user_id')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->foreignId('responsible_user_id')
                    ->nullable()
                    ->constrained('users', indexName: 'support_tickets_responsible_user_fk')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('support_tickets', 'conflict_user_ids')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->json('conflict_user_ids')->nullable();
            });
        }

        if (! Schema::hasColumn('support_tickets', 'protective_action_summary')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->string('protective_action_summary', 240)->nullable();
            });
        }

        if (! Schema::hasIndex('support_tickets', 'support_tickets_confidential_case_index')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->index(['confidential_case_group', 'status'], 'support_tickets_confidential_case_index');
            });
        }

        if (! Schema::hasTable('support_ticket_confidential_audits')) {
            Schema::create('support_ticket_confidential_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained(indexName: 'support_ticket_audits_ticket_fk')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'support_ticket_audits_actor_fk')->nullOnDelete();
                $table->string('event', 80);
                $table->json('metadata')->nullable();
                $table->string('previous_hash', 64)->nullable();
                $table->string('event_hash', 64);
                $table->timestamps();

                $table->unique(['support_ticket_id', 'event_hash'], 'support_ticket_confidential_audit_hash_unique');
                $table->index(['support_ticket_id', 'id'], 'support_ticket_confidential_audit_chain_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_confidential_audits');

        if (Schema::hasIndex('support_tickets', 'support_tickets_confidential_case_index')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->dropIndex('support_tickets_confidential_case_index');
            });
        }

        if (Schema::hasColumn('support_tickets', 'responsible_user_id')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('responsible_user_id');
            });
        }

        foreach (['confidential_case_group', 'conflict_user_ids', 'protective_action_summary'] as $column) {
            if (Schema::hasColumn('support_tickets', $column)) {
                Schema::table('support_tickets', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
