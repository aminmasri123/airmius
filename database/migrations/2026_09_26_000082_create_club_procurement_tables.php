<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_procurement_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_budget_id')->nullable()->constrained('club_budgets')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('supplier', 180)->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedInteger('estimated_total_cents')->default(0);
            $table->unsignedInteger('ordered_total_cents')->default(0);
            $table->unsignedInteger('received_total_cents')->default(0);
            $table->string('finance_account', 40)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'club_budget_id']);
        });

        Schema::create('club_procurement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_procurement_request_id')->constrained('club_procurement_requests')->cascadeOnDelete();
            $table->foreignId('club_inventory_item_id')->nullable()->constrained('club_inventory_items')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('sku', 120)->nullable();
            $table->string('unit', 40)->default('piece');
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_ordered')->default(0);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('unit_price_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('club_procurement_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_procurement_request_id')->constrained('club_procurement_requests')->cascadeOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('club_finance_entry_id')->nullable()->constrained('club_finance_entries')->restrictOnDelete();
            $table->date('received_on');
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_procurement_receipts');
        Schema::dropIfExists('club_procurement_items');
        Schema::dropIfExists('club_procurement_requests');
    }
};
