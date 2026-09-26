<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sepa_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_sepa_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_sepa_batch_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->string('status', 20)->default('prepared');
            $table->string('error_code', 40)->nullable();
            $table->string('message_id', 512)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['club_sepa_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_notices');
    }
};
