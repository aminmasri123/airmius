<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'participant_response_required')) {
                $table->boolean('participant_response_required')->default(false)->after('reminder_at');
            }

            if (! Schema::hasColumn('events', 'participant_response_deadline_at')) {
                $table->timestamp('participant_response_deadline_at')->nullable()->after('participant_response_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'participant_response_deadline_at')) {
                $table->dropColumn('participant_response_deadline_at');
            }

            if (Schema::hasColumn('events', 'participant_response_required')) {
                $table->dropColumn('participant_response_required');
            }
        });
    }
};

