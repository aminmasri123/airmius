<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        Schema::table('commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_orders', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('commerce_orders', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }

            if (! Schema::hasColumn('commerce_orders', 'access_token')) {
                $table->string('access_token', 80)->nullable()->unique()->after('guest_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            foreach (['access_token', 'guest_email', 'guest_name'] as $column) {
                if (Schema::hasColumn('commerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
