<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('posting_policy')->index();
            }
        });

        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('read_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasColumn('messages', 'scheduled_at')) {
                $table->dropIndex(['scheduled_at']);
                $table->dropColumn('scheduled_at');
            }
        });

        Schema::table('conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('conversations', 'scheduled_at')) {
                $table->dropIndex(['scheduled_at']);
                $table->dropColumn('scheduled_at');
            }
        });
    }
};
