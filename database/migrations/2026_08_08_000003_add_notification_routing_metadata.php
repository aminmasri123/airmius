<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('category', 32)->default('system')->after('type')->index();
            $table->string('priority', 16)->default('normal')->after('category')->index();
            $table->char('dedupe_key', 64)->nullable()->after('priority');
            $table->unique(['user_id', 'dedupe_key'], 'notifications_user_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_dedupe_unique');
            $table->dropIndex(['category']);
            $table->dropIndex(['priority']);
            $table->dropColumn(['category', 'priority', 'dedupe_key']);
        });
    }
};
