<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_policy_documents', function (Blueprint $table) {
            $table->string('workflow_status', 32)->default('draft')->after('review_job_id');
            $table->string('classification', 32)->default('internal')->after('workflow_status');
            $table->date('retention_until')->nullable()->after('classification');
            $table->foreignId('approved_by')->nullable()->after('retention_until')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('published_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by');
            $table->string('publication_checksum', 64)->nullable()->after('published_at');
            $table->timestamp('archived_at')->nullable()->after('publication_checksum');

            $table->index(['club_id', 'workflow_status'], 'club_policy_documents_workflow_lookup');
            $table->index(['club_id', 'classification'], 'club_policy_documents_classification_lookup');
            $table->index(['club_id', 'retention_until'], 'club_policy_documents_retention_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('club_policy_documents', function (Blueprint $table) {
            $table->dropIndex('club_policy_documents_workflow_lookup');
            $table->dropIndex('club_policy_documents_classification_lookup');
            $table->dropIndex('club_policy_documents_retention_lookup');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn([
                'workflow_status',
                'classification',
                'retention_until',
                'approved_at',
                'published_at',
                'publication_checksum',
                'archived_at',
            ]);
        });
    }
};
