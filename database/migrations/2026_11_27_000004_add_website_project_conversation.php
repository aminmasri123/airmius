<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            $table->string('project_title')->nullable();
            $table->text('target_audience')->nullable();
            $table->json('inspiration_urls')->nullable();
        });
        Schema::create('website_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('comment');
            $table->string('status')->nullable();
            $table->text('body')->nullable();
            $table->string('request_key', 80)->nullable();
            $table->timestamps();
            $table->unique(['website_request_id', 'user_id', 'request_key'], 'website_message_retry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_request_messages');
        Schema::table('website_requests', fn (Blueprint $table) => $table->dropColumn(['project_title', 'target_audience', 'inspiration_urls']));
    }
};
