<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('email_verified_at');
            $table->string('guardian_email')->nullable()->after('birth_date');
            $table->foreignId('guardian_user_id')->nullable()->after('guardian_email')->constrained('users')->nullOnDelete();
            $table->timestamp('guardian_consent_requested_at')->nullable()->after('guardian_user_id');
            $table->timestamp('guardian_consent_at')->nullable()->after('guardian_consent_requested_at');
            $table->string('guardian_consent_token', 80)->nullable()->unique()->after('guardian_consent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['guardian_user_id']);
            $table->dropUnique(['guardian_consent_token']);
            $table->dropColumn([
                'birth_date',
                'guardian_email',
                'guardian_user_id',
                'guardian_consent_requested_at',
                'guardian_consent_at',
                'guardian_consent_token',
            ]);
        });
    }
};
