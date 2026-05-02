<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('reportable');
            $table->string('reason', 80);
            $table->text('details')->nullable();
            $table->string('status', 40)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_flags', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('flaggable');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40)->default('automatic');
            $table->string('severity', 40)->default('low');
            $table->json('categories')->nullable();
            $table->json('matched_terms')->nullable();
            $table->string('status', 40)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        foreach (['posts', 'comments', 'messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'moderation_status')) {
                    $table->string('moderation_status', 40)->default('approved')->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['messages', 'comments', 'posts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'moderation_status')) {
                    $table->dropColumn('moderation_status');
                }
            });
        }

        Schema::dropIfExists('moderation_flags');
        Schema::dropIfExists('content_reports');
    }
};
