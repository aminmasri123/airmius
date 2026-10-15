<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_deletion_financial_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('club_id_snapshot');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->timestamp('exported_at');
            $table->timestamp('archive_confirmed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_deletion_financial_reviews');
    }
};
