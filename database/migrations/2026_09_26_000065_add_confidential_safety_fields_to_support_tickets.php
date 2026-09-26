<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->boolean('is_confidential')->default(false)->after('status');
            $table->boolean('is_anonymous')->default(false)->after('is_confidential');
            $table->boolean('allow_follow_up')->default(true)->after('is_anonymous');
            $table->string('safety_report_type', 80)->nullable()->after('allow_follow_up');
            $table->string('affected_person_reference', 160)->nullable()->after('safety_report_type');
            $table->string('report_source', 80)->nullable()->after('affected_person_reference');
            $table->timestamp('confidential_at')->nullable()->after('report_source');
            $table->index(['is_confidential', 'status', 'priority'], 'support_tickets_confidential_index');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_confidential_index');
            $table->dropColumn([
                'is_confidential',
                'is_anonymous',
                'allow_follow_up',
                'safety_report_type',
                'affected_person_reference',
                'report_source',
                'confidential_at',
            ]);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
