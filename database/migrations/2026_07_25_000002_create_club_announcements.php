<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience_type')->default('all_members');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'published_at']);
            $table->index(['club_id', 'audience_type']);
        });

        Schema::create('club_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_announcement_id')->constrained('club_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['club_announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_announcement_reads');
        Schema::dropIfExists('club_announcements');
    }
};
