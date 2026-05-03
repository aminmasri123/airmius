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
                if (! Schema::hasColumn($tableName, 'cancel_at_period_end')) {
                    $table->boolean('cancel_at_period_end')->default(false)->after('status');
                }

                if (! Schema::hasColumn($tableName, 'cancels_at')) {
                    $table->timestamp('cancels_at')->nullable()->after('current_period_ends_at');
                }

                if (! Schema::hasColumn($tableName, 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancels_at');
                }

                if (! Schema::hasColumn($tableName, 'last_renewed_at')) {
                    $table->timestamp('last_renewed_at')->nullable()->after('cancelled_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['club_subscriptions', 'user_subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['last_renewed_at', 'cancelled_at', 'cancels_at', 'cancel_at_period_end'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
