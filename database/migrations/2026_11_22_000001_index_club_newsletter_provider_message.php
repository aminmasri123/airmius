<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_newsletter_deliveries', function (Blueprint $table): void {
            $table->index('provider_message_id', 'club_newsletter_delivery_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::table('club_newsletter_deliveries', function (Blueprint $table): void {
            $table->dropIndex('club_newsletter_delivery_provider_idx');
        });
    }
};
