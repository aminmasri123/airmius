<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('team_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('conversations', 'name')) {
                $table->string('name', 120)->nullable()->after('type');
            }

            if (! Schema::hasColumn('conversations', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });

        Schema::table('conversation_users', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_users', 'muted_until')) {
                $table->timestamp('muted_until')->nullable()->after('joined_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversation_users', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_users', 'muted_until')) {
                $table->dropIndex(['muted_until']);
                $table->dropColumn('muted_until');
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('conversations', 'name')) {
                $table->dropColumn('name');
            }

            if (Schema::hasColumn('conversations', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }
        });
    }
};
