<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key')->index();
            $table->string('mail_type')->index();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email')->nullable()->index();
            $table->string('recipient_name')->nullable();
            $table->string('status')->index();
            $table->string('primary_category')->nullable();
            $table->string('fallback_category')->nullable();
            $table->string('used_category')->nullable();
            $table->string('mailer')->nullable();
            $table->string('from_address')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_deliveries');
    }
};
