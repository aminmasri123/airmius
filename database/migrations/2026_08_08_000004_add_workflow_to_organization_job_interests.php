<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_job_interests', function (Blueprint $table) {
            $table->string('status', 30)->default('new')->after('message')->index();
            $table->text('internal_note')->nullable()->after('status');
            $table->timestamp('consent_at')->nullable()->after('internal_note');
            $table->timestamp('status_changed_at')->nullable()->after('consent_at');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('retention_expires_at')->nullable()->after('status_changed_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('organization_job_interests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn([
                'status',
                'internal_note',
                'consent_at',
                'status_changed_at',
                'retention_expires_at',
            ]);
        });
    }
};
