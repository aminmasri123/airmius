<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question');
            $table->text('description')->nullable();
            $table->string('audience_type')->default('all_members');
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedTinyInteger('quorum')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'audience_type']);
        });

        Schema::create('club_survey_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_survey_id')->constrained('club_surveys')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('club_survey_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_survey_id')->constrained('club_surveys')->cascadeOnDelete();
            $table->foreignId('club_survey_option_id')->constrained('club_survey_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['club_survey_id', 'user_id']);
            $table->index(['club_survey_id', 'club_survey_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_survey_votes');
        Schema::dropIfExists('club_survey_options');
        Schema::dropIfExists('club_surveys');
    }
};
