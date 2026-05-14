<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('category', 80)->default('training');
            $table->string('sport_type', 80)->nullable();
            $table->string('level', 40)->default('beginner');
            $table->string('language', 10)->default('de');
            $table->string('cover_image')->nullable();
            $table->json('learning_goals')->nullable();
            $table->json('requirements')->nullable();
            $table->json('target_groups')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_free')->default(true);
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_course_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type', 30)->default('lesson');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
        });

        Schema::create('learning_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('pass_percent')->default(70);
            $table->timestamps();
        });

        Schema::create('learning_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->json('options')->nullable();
            $table->json('correct_options')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['learning_course_id', 'user_id'], 'learning_enrollments_course_user_unique');
        });

        Schema::create('learning_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_lesson_id')->constrained()->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('watch_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['learning_enrollment_id', 'learning_lesson_id'], 'learning_progress_enrollment_lesson_unique');
        });

        Schema::create('learning_lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('learning_lesson_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('learning_lesson_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility', 30)->default('course');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_lesson_comments');
        Schema::dropIfExists('learning_lesson_notes');
        Schema::dropIfExists('learning_lesson_progress');
        Schema::dropIfExists('learning_enrollments');
        Schema::dropIfExists('learning_quiz_questions');
        Schema::dropIfExists('learning_quizzes');
        Schema::dropIfExists('learning_lessons');
        Schema::dropIfExists('learning_course_sections');
        Schema::dropIfExists('learning_courses');
    }
};
