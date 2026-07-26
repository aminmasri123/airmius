<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('club_membership_requests', 'consent_version')) {
                $table->string('consent_version', 80)->nullable()->after('accepted_documents');
            }
            if (! Schema::hasColumn('club_membership_requests', 'consent_signature')) {
                $table->string('consent_signature', 255)->nullable()->after('consent_version');
            }
            if (! Schema::hasColumn('club_membership_requests', 'consent_ip')) {
                $table->string('consent_ip', 45)->nullable()->after('consent_signature');
            }
            if (! Schema::hasColumn('club_membership_requests', 'consent_user_agent')) {
                $table->string('consent_user_agent', 1000)->nullable()->after('consent_ip');
            }
            if (! Schema::hasColumn('club_membership_requests', 'consent_at')) {
                $table->timestamp('consent_at')->nullable()->after('consent_user_agent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table) {
            foreach (['consent_at', 'consent_user_agent', 'consent_ip', 'consent_signature', 'consent_version'] as $column) {
                if (Schema::hasColumn('club_membership_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
