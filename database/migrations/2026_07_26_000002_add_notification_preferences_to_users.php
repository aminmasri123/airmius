<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'notification_channels')) {
                $table->json('notification_channels')->nullable()->after('friend_request_privacy');
            }
            if (! Schema::hasColumn('users', 'notification_quiet_time')) {
                $table->string('notification_quiet_time', 20)->nullable()->after('notification_channels');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['notification_quiet_time', 'notification_channels'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
