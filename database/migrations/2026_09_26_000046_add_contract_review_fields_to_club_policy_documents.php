<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_policy_documents', function (Blueprint $table) {
            $table->date('contract_starts_on')->nullable()->after('valid_until');
            $table->date('contract_ends_on')->nullable()->after('contract_starts_on');
            $table->unsignedSmallInteger('cancellation_notice_days')->nullable()->after('contract_ends_on');
            $table->date('review_at')->nullable()->after('cancellation_notice_days');
            $table->foreignId('review_job_id')->nullable()->after('review_at')->constrained('work_automation_jobs')->nullOnDelete();
            $table->index(['club_id', 'review_at'], 'club_policy_documents_review_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('club_policy_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_job_id');
            $table->dropIndex('club_policy_documents_review_lookup');
            $table->dropColumn([
                'contract_starts_on',
                'contract_ends_on',
                'cancellation_notice_days',
                'review_at',
            ]);
        });
    }
};
