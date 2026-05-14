<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'payment_reminders_sent')) {
                $table->unsignedTinyInteger('payment_reminders_sent')->default(0)->after('payment_payload');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'last_payment_reminder_sent_at')) {
                $table->timestamp('last_payment_reminder_sent_at')->nullable()->after('payment_reminders_sent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            foreach (['last_payment_reminder_sent_at', 'payment_reminders_sent'] as $column) {
                if (Schema::hasColumn('outfit_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
