<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_outbox_events', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable()->after('available_at');
            $table->index(
                ['published_at', 'available_at', 'queued_at', 'occurred_at'],
                'domain_outbox_enqueue_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('domain_outbox_events', function (Blueprint $table): void {
            $table->dropIndex('domain_outbox_enqueue_idx');
            $table->dropColumn('queued_at');
        });
    }
};
