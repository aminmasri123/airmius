<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 40);
            $table->string('key', 80);
            $table->string('label', 160);
            $table->string('field_type', 30);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_sensitive')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['club_id', 'entity_type', 'key'], 'club_custom_fields_entity_key_unique');
            $table->index(['club_id', 'entity_type', 'is_active'], 'club_custom_fields_lookup');
        });

        Schema::create('club_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 40);
            $table->string('name', 160);
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['club_id', 'scope', 'name']);
            $table->index(['club_id', 'scope', 'is_active']);
        });

        Schema::create('club_number_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 40);
            $table->string('name', 160);
            $table->string('prefix', 40)->default('');
            $table->string('suffix', 40)->default('');
            $table->unsignedTinyInteger('padding')->default(5);
            $table->unsignedBigInteger('start_number')->default(1);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset_policy', 20)->default('never');
            $table->unsignedSmallInteger('last_reset_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['club_id', 'scope', 'name']);
            $table->index(['club_id', 'scope', 'is_active']);
        });

        Schema::create('club_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_number_range_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period_key')->default(0);
            $table->unsignedBigInteger('sequence_number');
            $table->string('formatted_number', 190);
            $table->uuid('allocation_key');
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['club_number_range_id', 'allocation_key'], 'club_number_allocations_idempotency_unique');
            $table->unique(['club_number_range_id', 'period_key', 'sequence_number'], 'club_number_allocations_sequence_unique');
            $table->unique(['club_number_range_id', 'formatted_number'], 'club_number_allocations_formatted_unique');
            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_number_allocations');
        Schema::dropIfExists('club_number_ranges');
        Schema::dropIfExists('club_categories');
        Schema::dropIfExists('club_custom_field_definitions');
    }
};
