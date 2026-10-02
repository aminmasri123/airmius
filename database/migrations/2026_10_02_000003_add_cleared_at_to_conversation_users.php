<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_users', function (Blueprint $table): void {
            $table->timestamp('cleared_at')->nullable()->after('muted_until');
            $table->unsignedBigInteger('cleared_message_id')->nullable()->after('cleared_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_users', function (Blueprint $table): void {
            $table->dropColumn(['cleared_at', 'cleared_message_id']);
        });
    }
};
