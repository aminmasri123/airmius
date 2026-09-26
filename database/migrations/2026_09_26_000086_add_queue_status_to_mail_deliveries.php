<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('mail_deliveries', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            }
            if (! Schema::hasColumn('mail_deliveries', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('context');
            }
            if (! Schema::hasColumn('mail_deliveries', 'last_attempt_at')) {
                $table->timestamp('last_attempt_at')->nullable()->after('queued_at');
            }
            if (! Schema::hasColumn('mail_deliveries', 'next_attempt_at')) {
                $table->timestamp('next_attempt_at')->nullable()->after('last_attempt_at');
            }
            if (! Schema::hasColumn('mail_deliveries', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('mail_deliveries', 'provider_status')) {
                $table->string('provider_status', 32)->nullable()->after('mailer');
            }
            if (! Schema::hasColumn('mail_deliveries', 'provider_message_id')) {
                $table->string('provider_message_id', 512)->nullable()->after('provider_status');
            }
            if (! Schema::hasColumn('mail_deliveries', 'adapter')) {
                $table->string('adapter', 80)->nullable()->after('provider_message_id');
            }

            $table->index(['status', 'next_attempt_at'], 'mail_deliveries_status_next_attempt_idx');
            $table->index(['provider_message_id'], 'mail_deliveries_provider_message_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table): void {
            $table->dropIndex('mail_deliveries_provider_message_idx');
            $table->dropIndex('mail_deliveries_status_next_attempt_idx');
            $table->dropColumn([
                'attempts',
                'queued_at',
                'last_attempt_at',
                'next_attempt_at',
                'failed_at',
                'provider_status',
                'provider_message_id',
                'adapter',
            ]);
        });
    }
};
