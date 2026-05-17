<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('moderation_status', 40)->default('approved');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('publisher_type', 20)->default('user');
            $table->unsignedBigInteger('publisher_id')->nullable();
            $table->string('visibility', 20)->default('public');
            $table->string('media_path');
            $table->string('media_thumbnail_path')->nullable();
            $table->string('media_type', 120);
            $table->unsignedBigInteger('media_size')->nullable();
            $table->string('caption', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['expires_at', 'moderation_status']);
            $table->index(['visibility', 'club_id', 'team_id']);
            $table->index(['publisher_type', 'publisher_id']);
        });

        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['story_id', 'user_id']);
        });

        Schema::create('story_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 20);
            $table->timestamps();

            $table->unique(['story_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_reactions');
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
    }
};
