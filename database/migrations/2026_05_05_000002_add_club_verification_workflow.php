<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'verification_status')) {
                $table->string('verification_status', 40)->default('verified')->after('official_club_number');
            }

            if (! Schema::hasColumn('clubs', 'requested_official_club_number')) {
                $table->string('requested_official_club_number', 120)->nullable()->after('verification_status');
            }

            if (! Schema::hasColumn('clubs', 'verification_notes')) {
                $table->text('verification_notes')->nullable()->after('requested_official_club_number');
            }

            if (! Schema::hasColumn('clubs', 'verification_requested_at')) {
                $table->timestamp('verification_requested_at')->nullable()->after('verification_notes');
            }

            if (! Schema::hasColumn('clubs', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_requested_at');
            }

            if (! Schema::hasColumn('clubs', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('verified_at');
            }

            if (! Schema::hasColumn('clubs', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }

            foreach ([
                'rejected_at',
                'verified_at',
                'verification_requested_at',
                'verification_notes',
                'requested_official_club_number',
                'verification_status',
            ] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
