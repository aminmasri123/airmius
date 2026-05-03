<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_checkouts', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('provider_customer_id');
            }

            if (! Schema::hasColumn('payment_checkouts', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_checkouts', function (Blueprint $table) {
            foreach (['due_at', 'payment_reference'] as $column) {
                if (Schema::hasColumn('payment_checkouts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
