<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_access_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'expires_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'guardian_consent_revoked_at')) {
                $table->timestamp('guardian_consent_revoked_at')
                    ->nullable()
                    ->after('guardian_consent_rejected_at');
            }

            if (! Schema::hasColumn('users', 'guardian_consent_revoked_by_email')) {
                $table->string('guardian_consent_revoked_by_email')
                    ->nullable()
                    ->after('guardian_consent_revoked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['guardian_consent_revoked_by_email', 'guardian_consent_revoked_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('guardian_access_codes');
    }
};
