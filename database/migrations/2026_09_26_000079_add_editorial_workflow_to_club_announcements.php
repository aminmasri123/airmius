<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_announcements', function (Blueprint $table): void {
            $table->string('content_type', 32)->default('message')->after('audience_type');
            $table->string('workflow_status', 32)->default('draft')->after('content_type');
            $table->timestamp('submitted_for_review_at')->nullable()->after('notified_at');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_for_review_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('withdrawn_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable()->after('withdrawn_by');
            $table->index(['club_id', 'workflow_status', 'content_type'], 'club_announcements_editorial_projection_idx');
        });

        DB::table('club_announcements')
            ->whereNotNull('published_at')
            ->update(['workflow_status' => 'published']);
    }

    public function down(): void
    {
        Schema::table('club_announcements', function (Blueprint $table): void {
            $table->dropIndex('club_announcements_editorial_projection_idx');
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'content_type',
                'workflow_status',
                'submitted_for_review_at',
                'reviewed_at',
                'withdrawn_at',
            ]);
        });
    }
};
