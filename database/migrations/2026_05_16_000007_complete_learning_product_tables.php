<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_quiz_attempts')) {
            Schema::create('learning_quiz_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_quiz_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_enrollment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->json('answers')->nullable();
                $table->json('correct_question_ids')->nullable();
                $table->unsignedTinyInteger('score_percent')->default(0);
                $table->boolean('passed')->default(false);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('learning_certificates')) {
            Schema::create('learning_certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_enrollment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('code')->unique();
                $table->timestamp('issued_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('learning_course_reviews')) {
            Schema::create('learning_course_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('body')->nullable();
                $table->string('status', 30)->default('published');
                $table->timestamps();
                $table->unique(['learning_course_id', 'user_id'], 'learning_reviews_course_user_unique');
            });
        }

        Schema::table('learning_lesson_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_lesson_comments', 'status')) {
                $table->string('status', 30)->default('open')->after('visibility');
            }

            if (! Schema::hasColumn('learning_lesson_comments', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_lesson_comments', function (Blueprint $table) {
            if (Schema::hasColumn('learning_lesson_comments', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }

            if (Schema::hasColumn('learning_lesson_comments', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::dropIfExists('learning_course_reviews');
        Schema::dropIfExists('learning_certificates');
        Schema::dropIfExists('learning_quiz_attempts');
    }
};
