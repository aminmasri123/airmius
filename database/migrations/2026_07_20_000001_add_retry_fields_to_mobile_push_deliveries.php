<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_push_deliveries', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('queued_at');
            $table->timestamp('next_attempt_at')->nullable()->after('last_attempt_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mobile_push_deliveries', function (Blueprint $table) {
            $table->dropIndex(['next_attempt_at']);
            $table->dropColumn(['attempts', 'last_attempt_at', 'next_attempt_at']);
        });
    }
};
