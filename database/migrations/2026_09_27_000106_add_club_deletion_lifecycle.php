<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('deletion_scheduled_at')->nullable()->index();
            $table->foreignId('deletion_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('deletion_owner_id')->nullable();
            $table->timestamp('deletion_reminded_at')->nullable();
            $table->timestamp('deletion_blocked_at')->nullable();
        });

        // Survives removal of the club so storage failures can be retried.
        Schema::create('club_deletion_file_cleanups', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->json('paths');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_deletion_file_cleanups');
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deletion_requested_by');
            $table->dropColumn(['deletion_requested_at', 'deletion_scheduled_at', 'deletion_reminded_at', 'deletion_blocked_at', 'deletion_owner_id']);
        });
    }
};
