<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_staff_availabilities')) {
            Schema::create('club_staff_availabilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_staff_availabilities_club_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained(indexName: 'club_staff_availabilities_user_fk')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'club_staff_availabilities_creator_fk')->nullOnDelete();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('status', 32)->default('available');
                $table->string('source', 40)->default('manual');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'user_id', 'starts_at', 'ends_at'], 'staff_availability_member_window_index');
                $table->index(['club_id', 'status', 'starts_at'], 'club_staff_availabilities_status_start_idx');
            });
        }

        if (! Schema::hasTable('club_staff_assignments')) {
            Schema::create('club_staff_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_staff_assignments_club_fk')->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained(indexName: 'club_staff_assignments_event_fk')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained(indexName: 'club_staff_assignments_team_fk')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained(indexName: 'club_staff_assignments_user_fk')->nullOnDelete();
                $table->foreignId('substitute_user_id')->nullable()->constrained('users', indexName: 'club_staff_assignments_sub_fk')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users', indexName: 'club_staff_assignments_assigner_fk')->nullOnDelete();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('role', 80);
                $table->json('required_qualifications')->nullable();
                $table->string('status', 32)->default('planned');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'user_id', 'starts_at', 'ends_at'], 'staff_assignment_member_window_index');
                $table->index(['club_id', 'event_id'], 'club_staff_assignments_club_event_idx');
                $table->index(['club_id', 'team_id'], 'club_staff_assignments_club_team_idx');
                $table->index(['club_id', 'status'], 'club_staff_assignments_club_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_staff_assignments');
        Schema::dropIfExists('club_staff_availabilities');
    }
};
