<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('posts', 'visibility')) {
                $table->string('visibility', 20)->default('organization')->after('team_id');
            }
        });

        Schema::table('files', function (Blueprint $table) {
            if (! Schema::hasColumn('files', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE files MODIFY club_id BIGINT UNSIGNED NULL');
        }

        Schema::create('post_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_attachments');

        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }

            if (Schema::hasColumn('posts', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};
