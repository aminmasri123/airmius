<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'membership_application_fields')) {
                $table->json('membership_application_fields')->nullable()->after('member_pause_requests_enabled');
            }

            if (! Schema::hasColumn('clubs', 'membership_payment_methods')) {
                $table->json('membership_payment_methods')->nullable()->after('membership_application_fields');
            }
        });

        Schema::table('club_membership_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('club_membership_requests', 'application_data')) {
                $table->json('application_data')->nullable()->after('message');
            }

            if (! Schema::hasColumn('club_membership_requests', 'preferred_payment_method')) {
                $table->string('preferred_payment_method', 40)->nullable()->after('application_data');
            }

            if (! Schema::hasColumn('club_membership_requests', 'requested_billing_interval')) {
                $table->string('requested_billing_interval', 30)->nullable()->after('preferred_payment_method');
            }

            if (! Schema::hasColumn('club_membership_requests', 'applicant_confirmed_at')) {
                $table->timestamp('applicant_confirmed_at')->nullable()->after('requested_billing_interval');
            }
        });

        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'payment_method')) {
                $table->string('payment_method', 40)->nullable()->after('contribution_interval');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            if (Schema::hasColumn('club_user', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });

        Schema::table('club_membership_requests', function (Blueprint $table) {
            foreach (['applicant_confirmed_at', 'requested_billing_interval', 'preferred_payment_method', 'application_data'] as $column) {
                if (Schema::hasColumn('club_membership_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            foreach (['membership_payment_methods', 'membership_application_fields'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
