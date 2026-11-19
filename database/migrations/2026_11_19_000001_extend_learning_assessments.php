<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_quizzes', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedTinyInteger('schema_version')->default(1);
        });
        Schema::table('learning_quiz_questions', function (Blueprint $table) {
            $table->string('question_type', 30)->default('multiple_choice');
            $table->text('image_url')->nullable();
        });
        Schema::table('learning_quiz_attempts', function (Blueprint $table) {
            $table->json('question_snapshot')->nullable();
            $table->json('manual_grades')->nullable();
            $table->string('review_status', 20)->default('automatic');
            $table->text('feedback')->nullable();
            $table->unsignedInteger('quiz_revision')->nullable();
            $table->unsignedTinyInteger('pass_percent_snapshot')->nullable();
        });
        Schema::table('learning_assignments', function (Blueprint $table) {
            $table->json('questions')->nullable();
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::table('learning_assignment_submissions', function (Blueprint $table) {
            $table->json('answers')->nullable();
            $table->json('question_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_quizzes', fn (Blueprint $table) => $table->dropColumn(['revision', 'schema_version']));
        Schema::table('learning_quiz_questions', fn (Blueprint $table) => $table->dropColumn(['question_type', 'image_url']));
        Schema::table('learning_quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['question_snapshot', 'manual_grades', 'review_status', 'feedback', 'quiz_revision', 'pass_percent_snapshot']));
        Schema::table('learning_assignments', fn (Blueprint $table) => $table->dropColumn(['questions', 'revision']));
        Schema::table('learning_assignment_submissions', fn (Blueprint $table) => $table->dropColumn(['answers', 'question_snapshot']));
    }
};
