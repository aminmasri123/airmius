<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('club_tasks')) {
            Schema::table('club_tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('club_tasks', 'team_id')) {
                    $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('club_tasks', 'assigned_to')) {
                    $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('club_tasks', 'description')) {
                    $table->text('description')->nullable()->after('title');
                }
                if (! Schema::hasColumn('club_tasks', 'status')) {
                    $table->string('status')->default('open')->after('description');
                }
                if (! Schema::hasColumn('club_tasks', 'priority')) {
                    $table->string('priority')->default('normal')->after('status');
                }
                if (! Schema::hasColumn('club_tasks', 'visibility')) {
                    $table->string('visibility')->default('club')->after('priority');
                }
                if (! Schema::hasColumn('club_tasks', 'start_at')) {
                    $table->date('start_at')->nullable()->after('visibility');
                }
                if (! Schema::hasColumn('club_tasks', 'due_at')) {
                    $table->date('due_at')->nullable()->after('start_at');
                }
                if (! Schema::hasColumn('club_tasks', 'participant_ids')) {
                    $table->json('participant_ids')->nullable()->after('due_at');
                }
                if (! Schema::hasColumn('club_tasks', 'checklist')) {
                    $table->json('checklist')->nullable()->after('participant_ids');
                }
            });
        }

        if (! Schema::hasTable('club_task_comments')) {
            Schema::create('club_task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->text('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('club_task_attachments')) {
            Schema::create('club_task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('file_id')->constrained()->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['club_task_id', 'file_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_task_attachments');
        Schema::dropIfExists('club_task_comments');

        if (Schema::hasTable('club_tasks')) {
            Schema::table('club_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('club_tasks', 'team_id')) {
                    $table->dropConstrainedForeignId('team_id');
                }
                if (Schema::hasColumn('club_tasks', 'assigned_to')) {
                    $table->dropConstrainedForeignId('assigned_to');
                }
                foreach (['description', 'status', 'priority', 'visibility', 'start_at', 'due_at', 'participant_ids', 'checklist'] as $column) {
                    if (Schema::hasColumn('club_tasks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
