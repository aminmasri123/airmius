<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            $table->text('information_request_message')->nullable()->after('message');
            $table->foreignId('information_requested_by')->nullable()->after('information_request_message')->constrained('users')->nullOnDelete();
            $table->timestamp('information_requested_at')->nullable()->after('information_requested_by');
            $table->text('applicant_response_message')->nullable()->after('information_requested_at');
            $table->timestamp('applicant_responded_at')->nullable()->after('applicant_response_message');
            $table->foreignId('waitlisted_by')->nullable()->after('applicant_responded_at')->constrained('users')->nullOnDelete();
            $table->timestamp('waitlisted_at')->nullable()->after('waitlisted_by');
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('information_requested_by');
            $table->dropConstrainedForeignId('waitlisted_by');
            $table->dropColumn([
                'information_request_message',
                'information_requested_at',
                'applicant_response_message',
                'applicant_responded_at',
                'waitlisted_at',
            ]);
        });
    }
};
