<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_dunning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name');
            $table->json('stages');
            $table->json('exceptions')->nullable();
            $table->json('channel_requirements')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'version']);
            $table->index(['club_id', 'is_active']);
        });

        Schema::create('club_dunning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_dunning_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rule_version')->nullable();
            $table->unsignedInteger('stage');
            $table->string('status', 32)->default('recorded');
            $table->string('channel', 32);
            $table->string('delivery_status', 32)->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->integer('fee_cents')->default(0);
            $table->string('fee_invoice_number')->nullable();
            $table->boolean('blocks_service')->default(false);
            $table->boolean('is_exception')->default(false);
            $table->string('exception_reason')->nullable();
            $table->string('idempotency_key');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'idempotency_key']);
            $table->index(['club_id', 'invoice_id', 'stage']);
            $table->index(['club_id', 'blocks_service', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_dunning_events');
        Schema::dropIfExists('club_dunning_rules');
    }
};
