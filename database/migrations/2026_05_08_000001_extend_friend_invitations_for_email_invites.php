<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('friend_invitations', 'recipient_id') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE friend_invitations MODIFY recipient_id BIGINT UNSIGNED NULL');
        }

        Schema::table('friend_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('friend_invitations', 'recipient_id') && DB::getDriverName() !== 'mysql') {
                $table->foreignId('recipient_id')->nullable()->change();
            }

            if (! Schema::hasColumn('friend_invitations', 'email')) {
                $table->string('email')->nullable()->after('recipient_id');
                $table->unique(['sender_id', 'email']);
                $table->index(['email', 'status']);
            }

            if (! Schema::hasColumn('friend_invitations', 'token')) {
                $table->string('token', 80)->nullable()->unique()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('friend_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('friend_invitations', 'token')) {
                $table->dropUnique(['token']);
                $table->dropColumn('token');
            }

            if (Schema::hasColumn('friend_invitations', 'email')) {
                $table->dropUnique(['sender_id', 'email']);
                $table->dropIndex(['email', 'status']);
                $table->dropColumn('email');
            }
        });
    }
};
