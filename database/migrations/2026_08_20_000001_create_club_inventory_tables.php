<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity_total')->default(1);
            $table->unsignedInteger('quantity_available')->default(1);
            $table->string('condition')->default('good');
            $table->string('status')->default('active');
            $table->uuid('qr_token')->unique();
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->unique(['club_id', 'sku']);
        });

        Schema::create('club_inventory_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('club_inventory_item_id')->constrained('club_inventory_items')->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('active');
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status', 'due_at']);
            $table->index(['borrower_id', 'status']);
        });

        Schema::create('club_inventory_maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('club_inventory_item_id')->constrained('club_inventory_items')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_inventory_maintenance_records');
        Schema::dropIfExists('club_inventory_loans');
        Schema::dropIfExists('club_inventory_items');
    }
};
