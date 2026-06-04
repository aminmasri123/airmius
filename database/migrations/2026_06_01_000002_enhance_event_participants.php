<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_participants', 'response_reason')) {
                    $table->text('response_reason')->nullable()->after('status');
                }

                if (! Schema::hasColumn('event_participants', 'response_mode')) {
                    $table->string('response_mode', 20)->nullable()->after('response_reason');
                }

                if (! Schema::hasColumn('event_participants', 'responded_at')) {
                    $table->timestamp('responded_at')->nullable()->after('response_mode');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE event_participants MODIFY status ENUM('yes','no','maybe','late') NOT NULL");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table): void {
                foreach (['response_reason', 'response_mode', 'responded_at'] as $column) {
                    if (Schema::hasColumn('event_participants', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

