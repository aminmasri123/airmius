<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_log_feedback', function (Blueprint $table): void {
            $table->string('classification', 40)->default('protected_training_record')->after('role');
            $table->timestamp('retention_until')->nullable()->after('classification');
            $table->json('access_policy')->nullable()->after('retention_until');
        });
    }

    public function down(): void
    {
        Schema::table('training_log_feedback', function (Blueprint $table): void {
            $table->dropColumn(['classification', 'retention_until', 'access_policy']);
        });
    }
};
