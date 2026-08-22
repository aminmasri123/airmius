<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_contact_requests', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('internal_notes')->index();
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_contact_requests', function (Blueprint $table) {
            $table->dropForeign(['status_changed_by']);
            $table->dropIndex(['status_changed_at']);
            $table->dropColumn(['internal_notes', 'status_changed_at', 'status_changed_by']);
        });
    }
};
