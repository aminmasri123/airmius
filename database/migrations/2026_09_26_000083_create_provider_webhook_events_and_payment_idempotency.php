<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('scope', 40);
            $table->string('event_key', 128);
            $table->string('event_type', 80)->nullable();
            $table->string('status', 20)->default('received');
            $table->nullableMorphs('subject');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'scope', 'event_key'], 'provider_webhook_events_unique');
            $table->index(['provider', 'scope', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key', 160)->nullable()->after('notes');
                $table->unique(['club_id', 'idempotency_key'], 'payments_club_idempotency_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'idempotency_key')) {
                $table->dropUnique('payments_club_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::dropIfExists('provider_webhook_events');
    }
};
