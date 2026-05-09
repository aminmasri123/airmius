<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'payment_provider')) {
                $table->string('payment_provider', 30)->default('bank_transfer')->after('status');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'payment_status')) {
                $table->string('payment_status', 30)->default('pending')->after('payment_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('outfit_subscriptions', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('outfit_subscriptions', 'payment_provider')) {
                $table->dropColumn('payment_provider');
            }
        });
    }
};
