<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('mail_deliveries', 'club_id')) {
                $table->foreignId('club_id')->nullable()->after('mail_type')->constrained('clubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('mail_deliveries', 'template_key')) {
                $table->string('template_key')->nullable()->after('status');
            }
            if (! Schema::hasColumn('mail_deliveries', 'subject')) {
                $table->string('subject')->nullable()->after('template_key');
            }
            if (! Schema::hasColumn('mail_deliveries', 'body')) {
                $table->text('body')->nullable()->after('subject');
            }
            if (! Schema::hasColumn('mail_deliveries', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('context');
            }
            if (! Schema::hasColumn('mail_deliveries', 'timezone')) {
                $table->string('timezone', 80)->nullable()->after('scheduled_at');
            }
            if (! Schema::hasColumn('mail_deliveries', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('mail_deliveries', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }

            $table->index(['club_id', 'status', 'scheduled_at'], 'mail_deliveries_club_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table): void {
            $table->dropIndex('mail_deliveries_club_status_scheduled_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['cancelled_at', 'timezone', 'scheduled_at', 'body', 'subject', 'template_key']);
            $table->dropConstrainedForeignId('club_id');
        });
    }
};
