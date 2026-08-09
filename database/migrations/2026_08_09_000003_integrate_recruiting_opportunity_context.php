<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_jobs', function (Blueprint $table) {
            $table->foreignId('sport_id')
                ->nullable()
                ->after('club_id')
                ->constrained('sports')
                ->nullOnDelete();
            $table->string('minimum_experience_level', 30)
                ->nullable()
                ->after('type');
            $table->index(['sport_id', 'minimum_experience_level'], 'org_jobs_sport_experience_idx');
        });

        Schema::table('organization_job_interests', function (Blueprint $table) {
            $table->json('shared_profile_fields')->nullable()->after('message');
            $table->timestamp('profile_consent_at')->nullable()->after('consent_at');
            $table->boolean('allow_in_app_contact')->default(false)->after('profile_consent_at');
            $table->foreignId('conversation_id')
                ->nullable()
                ->after('allow_in_app_contact')
                ->constrained('conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_job_interests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn([
                'shared_profile_fields',
                'profile_consent_at',
                'allow_in_app_contact',
            ]);
        });

        Schema::table('organization_jobs', function (Blueprint $table) {
            $table->dropIndex('org_jobs_sport_experience_idx');
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn('minimum_experience_level');
        });
    }
};
