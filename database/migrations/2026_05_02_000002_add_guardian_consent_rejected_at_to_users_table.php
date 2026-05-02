<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'guardian_consent_rejected_at')) {
                $table->timestamp('guardian_consent_rejected_at')
                    ->nullable()
                    ->after('guardian_consent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'guardian_consent_rejected_at')) {
                $table->dropColumn('guardian_consent_rejected_at');
            }
        });
    }
};
