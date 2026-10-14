<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_staff_assignments', function (Blueprint $table): void {
            $table->uuid('shift_key')->nullable()->after('club_id');
            $table->foreignId('external_member_id')->nullable()->after('user_id')->constrained('club_external_members')->nullOnDelete();
            $table->string('visibility_scope', 24)->default('club')->after('assigned_by');
            $table->json('audience_team_ids')->nullable()->after('visibility_scope');
            $table->json('audience_user_ids')->nullable()->after('audience_team_ids');
            $table->boolean('notify_audience')->default(true)->after('audience_user_ids');
            $table->string('location')->nullable()->after('role');
            $table->text('instructions')->nullable()->after('location');
            $table->text('requirements')->nullable()->after('instructions');
            $table->string('contact_name')->nullable()->after('requirements');
            $table->string('contact_details')->nullable()->after('contact_name');
            $table->timestamp('signup_deadline')->nullable()->after('ends_at');
            $table->index(['club_id', 'shift_key'], 'staff_assignments_club_shift_idx');
            $table->index(['external_member_id', 'starts_at'], 'staff_assignment_external_window_idx');
        });

        DB::table('club_staff_assignments')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $assignment) => implode('|', [
                $assignment->club_id,
                $assignment->event_id,
                $assignment->team_id,
                $assignment->role,
                $assignment->starts_at,
                $assignment->ends_at,
            ]))
            ->each(function ($assignments): void {
                DB::table('club_staff_assignments')
                    ->whereIn('id', $assignments->pluck('id'))
                    ->update(['shift_key' => (string) Str::uuid()]);
            });

        Schema::create('club_staff_shift_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->uuid('shift_key');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
            $table->index(['club_id', 'shift_key', 'created_at'], 'staff_shift_comments_timeline_idx');
        });

        Schema::table('club_staff_change_requests', function (Blueprint $table): void {
            $table->foreignId('source_assignment_id')->nullable()->after('assignment_id')->constrained('club_staff_assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_staff_change_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_assignment_id');
        });
        Schema::dropIfExists('club_staff_shift_comments');
        Schema::table('club_staff_assignments', function (Blueprint $table): void {
            $table->dropIndex('staff_assignments_club_shift_idx');
            $table->dropIndex('staff_assignment_external_window_idx');
            $table->dropConstrainedForeignId('external_member_id');
            $table->dropColumn([
                'shift_key', 'visibility_scope', 'audience_team_ids', 'audience_user_ids',
                'notify_audience', 'location', 'instructions', 'requirements', 'contact_name',
                'contact_details', 'signup_deadline',
            ]);
        });
    }
};
