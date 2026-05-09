<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'payment_due_at')) {
                $table->timestamp('payment_due_at')->nullable()->after('payment_reference');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'payment_payload')) {
                $table->json('payment_payload')->nullable()->after('payment_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            foreach (['payment_payload', 'payment_due_at', 'payment_reference'] as $column) {
                if (Schema::hasColumn('outfit_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
