<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_person_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('membership_user_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('privacy_level', 32)->default('internal');
            $table->json('emergency_contact')->nullable();
            $table->json('data_processing_flags')->nullable();
            $table->timestamp('consent_recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'user_id'], 'club_person_profiles_user_unique');
            $table->index(['club_id', 'display_name']);
            $table->index(['club_id', 'membership_user_id']);
        });

        Schema::create('club_employment_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_person_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_role_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('engagement_type', 40);
            $table->string('role_key', 80);
            $table->string('status', 32)->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('qualification_requirements')->nullable();
            $table->json('contract_terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'engagement_type', 'status'], 'club_engagement_type_status_index');
            $table->index(['club_id', 'role_key', 'status'], 'club_engagement_role_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_employment_engagements');
        Schema::dropIfExists('club_person_profiles');
    }
};
