<?php

use App\Models\OrganizationJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_jobs', function (Blueprint $table) {
            $table->json('required_qualifications')->nullable()->after('minimum_experience_level');
            $table->timestamp('starts_at')->nullable()->after('location');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->unsignedSmallInteger('shift_slots_required')->default(1)->after('ends_at');
            $table->string('commitment_type', 30)->default(OrganizationJob::COMMITMENT_VOLUNTARY)->after('shift_slots_required');

            $table->index(['club_id', 'commitment_type']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_jobs', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'commitment_type']);
            $table->dropIndex(['starts_at', 'ends_at']);
            $table->dropColumn([
                'required_qualifications',
                'starts_at',
                'ends_at',
                'shift_slots_required',
                'commitment_type',
            ]);
        });
    }
};
