<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_penalty_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('title');
            $table->string('trigger')->default('custom');
            $table->enum('calculation_type', ['fixed', 'per_minute', 'threshold_fixed', 'item'])->default('fixed');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('threshold_minutes')->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->string('unit_label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'trigger']);
        });

        Schema::table('team_fees', function (Blueprint $table): void {
            if (! Schema::hasColumn('team_fees', 'penalty_rule_id')) {
                $table->foreignId('penalty_rule_id')
                    ->nullable()
                    ->after('collector_id')
                    ->constrained('team_penalty_rules')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_fees', function (Blueprint $table): void {
            if (Schema::hasColumn('team_fees', 'penalty_rule_id')) {
                $table->dropConstrainedForeignId('penalty_rule_id');
            }
        });

        Schema::dropIfExists('team_penalty_rules');
    }
};
