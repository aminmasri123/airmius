<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'direct_message_privacy')) {
                $table->string('direct_message_privacy', 30)->default('everyone')->after('profile_visibility');
            }

            if (! Schema::hasColumn('users', 'friend_request_privacy')) {
                $table->string('friend_request_privacy', 30)->default('everyone')->after('direct_message_privacy');
            }
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'blocked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'friend_request_privacy')) {
                $table->dropColumn('friend_request_privacy');
            }

            if (Schema::hasColumn('users', 'direct_message_privacy')) {
                $table->dropColumn('direct_message_privacy');
            }
        });
    }
};
