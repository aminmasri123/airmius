<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120);
            $table->string('actor_key', 160);
            $table->string('scope', 255);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('response_headers')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['key', 'actor_key', 'scope'], 'api_idempotency_actor_scope_unique');
            $table->index('expires_at');
        });

        Schema::create('domain_outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_name', 160);
            $table->string('aggregate_type', 160);
            $table->string('aggregate_id', 160);
            $table->unsignedBigInteger('aggregate_version')->default(1);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->json('audience')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('available_at');
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'available_at'], 'domain_outbox_pending_index');
            $table->index(['aggregate_type', 'aggregate_id'], 'domain_outbox_aggregate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_outbox_events');
        Schema::dropIfExists('api_idempotency_keys');
    }
};
