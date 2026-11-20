<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_assignments', function (Blueprint $table): void {
            $table->string('grading_mode')->default('manual');
            $table->unsignedTinyInteger('pass_percent')->default(70);
        });
        Schema::table('learning_assignment_submissions', function (Blueprint $table): void {
            $table->json('grading_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_assignment_submissions', fn (Blueprint $table) => $table->dropColumn('grading_snapshot'));
        Schema::table('learning_assignments', fn (Blueprint $table) => $table->dropColumn(['grading_mode', 'pass_percent']));
    }
};
