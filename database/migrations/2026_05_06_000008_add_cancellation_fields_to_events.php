<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'status')) {
                $table->string('status', 30)->default('scheduled')->after('visibility');
            }

            if (! Schema::hasColumn('events', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('reminder_at');
            }

            if (! Schema::hasColumn('events', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('events', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }

            foreach (['cancellation_reason', 'cancelled_at', 'status'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
