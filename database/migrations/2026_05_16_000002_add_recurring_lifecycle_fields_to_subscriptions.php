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
                if (! Schema::hasColumn($tableName, 'billing_interval')) {
                    $table->string('billing_interval', 20)->nullable()->after('payment_provider');
                }

                if (! Schema::hasColumn($tableName, 'next_invoice_at')) {
                    $table->timestamp('next_invoice_at')->nullable()->after('current_period_ends_at');
                }

                if (! Schema::hasColumn($tableName, 'grace_period_ends_at')) {
                    $table->timestamp('grace_period_ends_at')->nullable()->after('next_invoice_at');
                }

                if (! Schema::hasColumn($tableName, 'access_restricted_at')) {
                    $table->timestamp('access_restricted_at')->nullable()->after('grace_period_ends_at');
                }
            });
        }

        Schema::table('subscription_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_invoices', 'subscription_type')) {
                $table->string('subscription_type', 30)->nullable()->after('subscription_plan_id');
            }

            if (! Schema::hasColumn('subscription_invoices', 'subscription_id')) {
                $table->unsignedBigInteger('subscription_id')->nullable()->after('subscription_type');
            }

            if (! Schema::hasColumn('subscription_invoices', 'reminder_count')) {
                $table->unsignedTinyInteger('reminder_count')->default(0)->after('reminder_email_sent_at');
            }

            if (! Schema::hasColumn('subscription_invoices', 'last_reminder_sent_at')) {
                $table->timestamp('last_reminder_sent_at')->nullable()->after('reminder_count');
            }

            $table->index(['subscription_type', 'subscription_id'], 'subscription_invoices_subscription_index');
            $table->index(['status', 'due_at'], 'subscription_invoices_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropIndex('subscription_invoices_status_due_index');
            $table->dropIndex('subscription_invoices_subscription_index');

            foreach (['last_reminder_sent_at', 'reminder_count', 'subscription_id', 'subscription_type'] as $column) {
                if (Schema::hasColumn('subscription_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        foreach (['club_subscriptions', 'user_subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['access_restricted_at', 'grace_period_ends_at', 'next_invoice_at', 'billing_interval'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
