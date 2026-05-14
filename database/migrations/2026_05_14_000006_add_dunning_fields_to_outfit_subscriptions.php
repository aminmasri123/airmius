<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'dunning_level')) {
                $table->unsignedTinyInteger('dunning_level')->default(0)->after('last_payment_reminder_sent_at');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'last_dunning_sent_at')) {
                $table->timestamp('last_dunning_sent_at')->nullable()->after('dunning_level');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'payment_paused_at')) {
                $table->timestamp('payment_paused_at')->nullable()->after('last_dunning_sent_at');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'payment_paused_reason')) {
                $table->string('payment_paused_reason')->nullable()->after('payment_paused_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            foreach (['payment_paused_reason', 'payment_paused_at', 'last_dunning_sent_at', 'dunning_level'] as $column) {
                if (Schema::hasColumn('outfit_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
