<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_lessons', 'unlock_after_days')) {
                $table->unsignedInteger('unlock_after_days')->default(0)->after('is_preview');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_lessons', function (Blueprint $table) {
            if (Schema::hasColumn('learning_lessons', 'unlock_after_days')) {
                $table->dropColumn('unlock_after_days');
            }
        });
    }
};
