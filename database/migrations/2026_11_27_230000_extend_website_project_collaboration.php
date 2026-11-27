<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
        });
        DB::table('website_requests')->whereIn('status', ['in_progress', 'done'])
            ->orWhereIn('id', DB::table('website_request_messages')->select('website_request_id')->whereIn('status', ['in_progress', 'done']))
            ->update(['processing_started_at' => now()]);
        Schema::create('website_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_request_message_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->string('disk', 80);
            $table->string('path');
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_request_attachments');
        Schema::table('website_requests', fn (Blueprint $table) => $table->dropColumn(['processing_started_at', 'withdrawn_at']));
    }
};
