<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_provider_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('area', 40);
            $table->string('provider', 80);
            $table->string('service', 80);
            $table->string('operation', 80);
            $table->unsignedInteger('billable_quantity')->default(1);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('status', 40)->default('ok');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['occurred_at', 'area', 'provider']);
            $table->index(['area', 'service', 'operation']);
            $table->index(['provider', 'service', 'operation']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_provider_usage_events');
    }
};
