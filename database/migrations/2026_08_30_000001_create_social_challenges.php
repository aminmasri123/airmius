<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('visibility', 24)->default('invite_only');
            $table->string('title', 140);
            $table->text('description')->nullable();
            $table->string('metric', 32)->default('steps');
            $table->decimal('target_value', 12, 2)->default(1);
            $table->string('unit', 24)->nullable();
            $table->string('frequency', 16)->default('daily');
            $table->string('verification', 16)->default('manual');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('published');
            $table->timestamps();

            $table->index(['visibility', 'status', 'starts_on', 'ends_on']);
            $table->index(['club_id', 'status']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('challenge_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->decimal('value', 12, 2)->nullable();
            $table->boolean('completed')->default(true);
            $table->string('source', 20)->default('manual');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id', 'checkin_date']);
            $table->index(['challenge_id', 'checkin_date', 'completed']);
        });

        Schema::create('challenge_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(['challenge_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_comments');
        Schema::dropIfExists('challenge_checkins');
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
    }
};
