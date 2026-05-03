<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['club_subscriptions', 'user_subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'cancellation_email_sent_at')) {
                    $table->timestamp('cancellation_email_sent_at')->nullable()->after('renewal_notified_at');
                }

                if (! Schema::hasColumn($tableName, 'renewal_email_sent_at')) {
                    $table->timestamp('renewal_email_sent_at')->nullable()->after('cancellation_email_sent_at');
                }

                if (! Schema::hasColumn($tableName, 'payment_issue_email_sent_at')) {
                    $table->timestamp('payment_issue_email_sent_at')->nullable()->after('renewal_email_sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['club_subscriptions', 'user_subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['payment_issue_email_sent_at', 'renewal_email_sent_at', 'cancellation_email_sent_at'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
