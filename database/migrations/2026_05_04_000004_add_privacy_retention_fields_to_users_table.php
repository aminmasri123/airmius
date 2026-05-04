<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'privacy_status')) {
                $table->string('privacy_status', 30)->default('active')->after('account_status');
            }

            if (! Schema::hasColumn('users', 'inactivity_first_warning_sent_at')) {
                $table->timestamp('inactivity_first_warning_sent_at')->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('users', 'inactivity_second_warning_sent_at')) {
                $table->timestamp('inactivity_second_warning_sent_at')->nullable()->after('inactivity_first_warning_sent_at');
            }

            if (! Schema::hasColumn('users', 'deletion_scheduled_at')) {
                $table->timestamp('deletion_scheduled_at')->nullable()->after('inactivity_second_warning_sent_at');
            }

            if (! Schema::hasColumn('users', 'anonymized_at')) {
                $table->timestamp('anonymized_at')->nullable()->after('deletion_scheduled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'anonymized_at',
                'deletion_scheduled_at',
                'inactivity_second_warning_sent_at',
                'inactivity_first_warning_sent_at',
                'privacy_status',
                'last_seen_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
