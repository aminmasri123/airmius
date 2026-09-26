<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_plans', 'club_training_group_id')) {
            Schema::table('training_plans', function (Blueprint $table) {
                $table->foreignId('club_training_group_id')->nullable()->after('team_id')->constrained('club_training_groups', indexName: 'training_plans_group_fk')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('training_plans', 'template_source_id')) {
            Schema::table('training_plans', function (Blueprint $table) {
                $table->foreignId('template_source_id')->nullable()->after('club_training_group_id')->constrained('training_plans', indexName: 'training_plans_template_fk')->nullOnDelete();
            });
        }

        foreach ([
            'period_type' => fn (Blueprint $table) => $table->string('period_type', 30)->default('week')->after('cadence'),
            'period_index' => fn (Blueprint $table) => $table->unsignedSmallInteger('period_index')->nullable()->after('period_type'),
            'season_label' => fn (Blueprint $table) => $table->string('season_label', 120)->nullable()->after('period_index'),
            'is_template' => fn (Blueprint $table) => $table->boolean('is_template')->default(false)->after('season_label'),
        ] as $column => $definition) {
            if (! Schema::hasColumn('training_plans', $column)) {
                Schema::table('training_plans', $definition);
            }
        }

        if (! Schema::hasIndex('training_plans', 'training_plans_group_period_idx')) {
            Schema::table('training_plans', function (Blueprint $table) {
                $table->index(['club_training_group_id', 'period_type'], 'training_plans_group_period_idx');
            });
        }

        if (! Schema::hasIndex('training_plans', 'training_plans_template_flag_idx')) {
            Schema::table('training_plans', function (Blueprint $table) {
                $table->index(['template_source_id', 'is_template'], 'training_plans_template_flag_idx');
            });
        }

        if (! Schema::hasColumn('training_plan_items', 'training_session_id')) {
            Schema::table('training_plan_items', function (Blueprint $table) {
                $table->foreignId('training_session_id')->nullable()->after('source_exercise_id')->constrained(indexName: 'training_plan_items_session_fk')->nullOnDelete();
            });
        }

        foreach ([
            'period_week' => fn (Blueprint $table) => $table->unsignedSmallInteger('period_week')->nullable()->after('scheduled_at'),
            'period_month' => fn (Blueprint $table) => $table->unsignedSmallInteger('period_month')->nullable()->after('period_week'),
        ] as $column => $definition) {
            if (! Schema::hasColumn('training_plan_items', $column)) {
                Schema::table('training_plan_items', $definition);
            }
        }

        if (! Schema::hasIndex('training_plan_items', 'training_plan_items_session_schedule_idx')) {
            Schema::table('training_plan_items', function (Blueprint $table) {
                $table->index(['training_session_id', 'scheduled_at'], 'training_plan_items_session_schedule_idx');
            });
        }

        if (! Schema::hasColumn('training_plan_assignments', 'club_training_group_id')) {
            Schema::table('training_plan_assignments', function (Blueprint $table) {
                $table->foreignId('club_training_group_id')->nullable()->after('team_id')->constrained('club_training_groups', indexName: 'training_plan_assignments_group_fk')->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('training_plan_assignments', 'training_plan_group_unique')) {
            Schema::table('training_plan_assignments', function (Blueprint $table) {
                $table->unique(['training_plan_id', 'club_training_group_id'], 'training_plan_group_unique');
            });
        }

        if (! Schema::hasIndex('training_plan_assignments', 'training_plan_assignments_group_perm_idx')) {
            Schema::table('training_plan_assignments', function (Blueprint $table) {
                $table->index(['club_training_group_id', 'permission'], 'training_plan_assignments_group_perm_idx');
            });
        }

        if (! Schema::hasTable('training_plan_handovers')) {
            Schema::create('training_plan_handovers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_plan_id')->constrained(indexName: 'training_plan_handovers_plan_fk')->cascadeOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users', indexName: 'training_plan_handovers_from_fk')->nullOnDelete();
                $table->foreignId('to_user_id')->constrained('users', indexName: 'training_plan_handovers_to_fk')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users', indexName: 'training_plan_handovers_creator_fk')->cascadeOnDelete();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('status', 30)->default('active');
                $table->text('handover_note')->nullable();
                $table->json('responsibilities')->nullable();
                $table->timestamps();
                $table->index(['training_plan_id', 'status'], 'training_plan_handovers_plan_status_idx');
                $table->index(['to_user_id', 'starts_at'], 'training_plan_handovers_to_start_idx');
            });
        }

        if (! Schema::hasTable('training_plan_history_entries')) {
            Schema::create('training_plan_history_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_plan_id')->constrained(indexName: 'training_plan_history_plan_fk')->cascadeOnDelete();
                $table->foreignId('training_plan_item_id')->nullable()->constrained(indexName: 'training_plan_history_item_fk')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'training_plan_history_actor_fk')->nullOnDelete();
                $table->string('event', 80);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['training_plan_id', 'created_at'], 'training_plan_history_plan_created_idx');
                $table->index(['event', 'created_at'], 'training_plan_history_event_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_history_entries');
        Schema::dropIfExists('training_plan_handovers');

        Schema::table('training_plan_assignments', function (Blueprint $table) {
            $table->dropUnique('training_plan_group_unique');
            $table->dropConstrainedForeignId('club_training_group_id');
        });

        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_session_id');
            $table->dropColumn(['period_week', 'period_month']);
        });

        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_source_id');
            $table->dropConstrainedForeignId('club_training_group_id');
            $table->dropColumn(['period_type', 'period_index', 'season_label', 'is_template']);
        });
    }
};
