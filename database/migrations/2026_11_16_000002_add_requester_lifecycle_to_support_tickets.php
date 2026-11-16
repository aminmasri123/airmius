<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('requester_hidden_at')->nullable();
            $table->index(['user_id', 'requester_hidden_at', 'id'], 'support_requester_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_requester_visibility_idx');
            $table->dropColumn(['withdrawn_at', 'requester_hidden_at']);
        });
    }
};
