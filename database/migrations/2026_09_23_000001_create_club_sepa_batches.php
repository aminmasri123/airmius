<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sepa_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 35)->unique();
            $table->string('status', 20)->default('draft');
            $table->date('collection_date');
            $table->unsignedSmallInteger('notice_days');
            $table->text('creditor_snapshot');
            $table->timestamp('approved_at')->nullable();
            $table->date('notice_sent_on')->nullable();
            $table->string('notice_channel', 30)->nullable();
            $table->text('notice_reference')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->longText('export_xml')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'status']);
        });
        Schema::create('club_sepa_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_sepa_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            // A cancelled batch keeps its history but releases this unique reservation.
            $table->foreignId('reserved_invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->text('debtor_snapshot');
            $table->timestamps();
            $table->unique(['club_sepa_batch_id', 'invoice_id'], 'sepa_batch_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_batch_items');
        Schema::dropIfExists('club_sepa_batches');
    }
};
