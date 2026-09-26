<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'min_participants')) {
                $table->unsignedInteger('min_participants')->nullable()->after('max_participants');
            }
        });

        Schema::table('event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_participants', 'lifecycle_status')) {
                $table->string('lifecycle_status')->default('reserved')->after('status');
            }
            if (! Schema::hasColumn('event_participants', 'payment_id')) {
                $table->foreignId('payment_id')->nullable()->after('user_id')->constrained('payments')->nullOnDelete();
            }
            if (! Schema::hasColumn('event_participants', 'refund_payment_id')) {
                $table->foreignId('refund_payment_id')->nullable()->after('payment_id')->constrained('payments')->nullOnDelete();
            }
            if (! Schema::hasColumn('event_participants', 'replacement_for_participant_id')) {
                $table->foreignId('replacement_for_participant_id')->nullable()->after('refund_payment_id')->constrained('event_participants')->nullOnDelete();
            }
            if (! Schema::hasColumn('event_participants', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('waitlist_offer_expires_at');
            }
            if (! Schema::hasColumn('event_participants', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('event_participants', 'lifecycle_idempotency_key')) {
                $table->string('lifecycle_idempotency_key')->nullable()->after('refunded_at');
                $table->unique(['event_id', 'lifecycle_idempotency_key'], 'event_participants_lifecycle_idempotency_unique');
            }
            if (! Schema::hasColumn('event_participants', 'lifecycle_note')) {
                $table->text('lifecycle_note')->nullable()->after('lifecycle_idempotency_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            if (Schema::hasColumn('event_participants', 'lifecycle_idempotency_key')) {
                $table->dropUnique('event_participants_lifecycle_idempotency_unique');
            }

            foreach ([
                'lifecycle_note',
                'lifecycle_idempotency_key',
                'refunded_at',
                'cancelled_at',
                'replacement_for_participant_id',
                'refund_payment_id',
                'payment_id',
                'lifecycle_status',
            ] as $column) {
                if (Schema::hasColumn('event_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'min_participants')) {
                $table->dropColumn('min_participants');
            }
        });
    }
};
