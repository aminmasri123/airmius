<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_membership_requests', 'requested_termination_on')) {
                $table->date('requested_termination_on')->nullable()->after('requested_pause_until');
            }

            if (! Schema::hasColumn('club_membership_requests', 'termination_reason')) {
                $table->text('termination_reason')->nullable()->after('requested_termination_on');
            }
        });

        Schema::table('club_user', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_user', 'membership_ended_at')) {
                $table->timestamp('membership_ended_at')->nullable()->after('membership_end_notified_at');
            }
        });

        Schema::table('club_external_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_external_members', 'membership_ended_at')) {
                $table->timestamp('membership_ended_at')->nullable()->after('membership_end_notified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_external_members', function (Blueprint $table): void {
            if (Schema::hasColumn('club_external_members', 'membership_ended_at')) {
                $table->dropColumn('membership_ended_at');
            }
        });

        Schema::table('club_user', function (Blueprint $table): void {
            if (Schema::hasColumn('club_user', 'membership_ended_at')) {
                $table->dropColumn('membership_ended_at');
            }
        });

        Schema::table('club_membership_requests', function (Blueprint $table): void {
            foreach (['termination_reason', 'requested_termination_on'] as $column) {
                if (Schema::hasColumn('club_membership_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
