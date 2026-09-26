<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_membership_requests', 'public_status_token')) {
                $table->string('public_status_token', 80)->nullable()->after('review_note');
                $table->index('public_status_token');
            }
        });

        Schema::table('learning_enrollments', function (Blueprint $table): void {
            if (! Schema::hasColumn('learning_enrollments', 'public_status_token')) {
                $table->string('public_status_token', 80)->nullable()->after('completed_at');
                $table->index('public_status_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_enrollments', function (Blueprint $table): void {
            if (Schema::hasColumn('learning_enrollments', 'public_status_token')) {
                $table->dropIndex(['public_status_token']);
                $table->dropColumn('public_status_token');
            }
        });

        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('club_membership_requests', 'public_status_token')) {
                $table->dropIndex(['public_status_token']);
                $table->dropColumn('public_status_token');
            }
        });
    }
};
