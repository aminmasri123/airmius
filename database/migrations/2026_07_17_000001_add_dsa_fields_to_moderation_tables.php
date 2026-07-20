<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('content_reports', 'decision_reason')) {
                $table->text('decision_reason')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('content_reports', 'action_taken')) {
                $table->string('action_taken', 80)->nullable()->after('decision_reason');
            }

            if (! Schema::hasColumn('content_reports', 'appeal_reason')) {
                $table->text('appeal_reason')->nullable()->after('action_taken');
            }

            if (! Schema::hasColumn('content_reports', 'appeal_status')) {
                $table->string('appeal_status', 40)->nullable()->after('appeal_reason');
            }

            if (! Schema::hasColumn('content_reports', 'appealed_at')) {
                $table->timestamp('appealed_at')->nullable()->after('appeal_status');
            }

            if (! Schema::hasColumn('content_reports', 'appeal_decision')) {
                $table->text('appeal_decision')->nullable()->after('appealed_at');
            }

            if (! Schema::hasColumn('content_reports', 'appeal_decided_by')) {
                $table->foreignId('appeal_decided_by')->nullable()->after('appeal_decision')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('content_reports', 'appeal_decided_at')) {
                $table->timestamp('appeal_decided_at')->nullable()->after('appeal_decided_by');
            }
        });

        Schema::table('moderation_flags', function (Blueprint $table) {
            if (! Schema::hasColumn('moderation_flags', 'decision_reason')) {
                $table->text('decision_reason')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('moderation_flags', 'action_taken')) {
                $table->string('action_taken', 80)->nullable()->after('decision_reason');
            }
        });

        if (! Schema::hasTable('moderation_logs')) {
            Schema::create('moderation_logs', function (Blueprint $table) {
                $table->id();
                $table->string('case_type');
                $table->unsignedBigInteger('case_id');
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 80);
                $table->string('previous_status', 80)->nullable();
                $table->string('new_status', 80)->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['case_type', 'case_id']);
                $table->index(['actor_id', 'action']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');

        Schema::table('moderation_flags', function (Blueprint $table) {
            foreach (['action_taken', 'decision_reason'] as $column) {
                if (Schema::hasColumn('moderation_flags', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('content_reports', function (Blueprint $table) {
            if (Schema::hasColumn('content_reports', 'appeal_decided_by')) {
                $table->dropConstrainedForeignId('appeal_decided_by');
            }

            foreach ([
                'appeal_decided_at',
                'appeal_decision',
                'appealed_at',
                'appeal_status',
                'appeal_reason',
                'action_taken',
                'decision_reason',
            ] as $column) {
                if (Schema::hasColumn('content_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
