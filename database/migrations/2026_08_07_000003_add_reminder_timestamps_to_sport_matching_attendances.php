<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_matching_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('sport_matching_attendances', 'reminder_24h_sent_at')) {
                $table->timestamp('reminder_24h_sent_at')->nullable();
            }

            if (! Schema::hasColumn('sport_matching_attendances', 'reminder_2h_sent_at')) {
                $table->timestamp('reminder_2h_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sport_matching_attendances', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('sport_matching_attendances', 'reminder_24h_sent_at')) {
                $columns[] = 'reminder_24h_sent_at';
            }
            if (Schema::hasColumn('sport_matching_attendances', 'reminder_2h_sent_at')) {
                $columns[] = 'reminder_2h_sent_at';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
