<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'membership_ends_on')) {
                $table->date('membership_ends_on')->nullable()->after('joined_on');
            }

            if (! Schema::hasColumn('club_user', 'membership_end_notified_at')) {
                $table->timestamp('membership_end_notified_at')->nullable()->after('membership_ends_on');
            }
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            if (! Schema::hasColumn('club_external_members', 'membership_ends_on')) {
                $table->date('membership_ends_on')->nullable()->after('joined_on');
            }

            if (! Schema::hasColumn('club_external_members', 'membership_end_notified_at')) {
                $table->timestamp('membership_end_notified_at')->nullable()->after('membership_ends_on');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'due_soon_notified_at')) {
                $table->timestamp('due_soon_notified_at')->nullable()->after('reminder_sent_at');
            }
        });

        Schema::table('club_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('club_subscriptions', 'renewal_notified_at')) {
                $table->timestamp('renewal_notified_at')->nullable()->after('current_period_ends_at');
            }
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('user_subscriptions', 'renewal_notified_at')) {
                $table->timestamp('renewal_notified_at')->nullable()->after('current_period_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('user_subscriptions', 'renewal_notified_at')) {
                $table->dropColumn('renewal_notified_at');
            }
        });

        Schema::table('club_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('club_subscriptions', 'renewal_notified_at')) {
                $table->dropColumn('renewal_notified_at');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'due_soon_notified_at')) {
                $table->dropColumn('due_soon_notified_at');
            }
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            foreach (['membership_end_notified_at', 'membership_ends_on'] as $column) {
                if (Schema::hasColumn('club_external_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('club_user', function (Blueprint $table) {
            foreach (['membership_end_notified_at', 'membership_ends_on'] as $column) {
                if (Schema::hasColumn('club_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
