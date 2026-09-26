<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_receipt_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('club_finance_entry_id')->nullable()->constrained('club_finance_entries')->nullOnDelete();
            $table->string('status', 32)->default('pending_confirmation');
            $table->string('scan_status', 32)->default('clean');
            $table->string('sha256', 64);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->json('ocr_suggestion')->nullable();
            $table->json('confirmed_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'sha256'], 'club_receipt_uploads_club_hash_unique');
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'club_finance_entry_id'], 'club_receipt_uploads_entry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_receipt_uploads');
    }
};
