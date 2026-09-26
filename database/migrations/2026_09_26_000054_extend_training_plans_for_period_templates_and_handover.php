<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->foreignId('club_training_group_id')->nullable()->after('team_id')->constrained('club_training_groups')->nullOnDelete();
            $table->foreignId('template_source_id')->nullable()->after('club_training_group_id')->constrained('training_plans')->nullOnDelete();
            $table->string('period_type', 30)->default('week')->after('cadence');
            $table->unsignedSmallInteger('period_index')->nullable()->after('period_type');
            $table->string('season_label', 120)->nullable()->after('period_index');
            $table->boolean('is_template')->default(false)->after('season_label');
            $table->index(['club_training_group_id', 'period_type']);
            $table->index(['template_source_id', 'is_template']);
        });

        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->foreignId('training_session_id')->nullable()->after('source_exercise_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('period_week')->nullable()->after('scheduled_at');
            $table->unsignedSmallInteger('period_month')->nullable()->after('period_week');
            $table->index(['training_session_id', 'scheduled_at']);
        });

        Schema::table('training_plan_assignments', function (Blueprint $table) {
            $table->foreignId('club_training_group_id')->nullable()->after('team_id')->constrained('club_training_groups')->cascadeOnDelete();
            $table->unique(['training_plan_id', 'club_training_group_id'], 'training_plan_group_unique');
            $table->index(['club_training_group_id', 'permission']);
        });

        Schema::create('training_plan_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('handover_note')->nullable();
            $table->json('responsibilities')->nullable();
            $table->timestamps();
            $table->index(['training_plan_id', 'status']);
            $table->index(['to_user_id', 'starts_at']);
        });

        Schema::create('training_plan_history_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_plan_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['training_plan_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
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
