<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_sepa_notices', function (Blueprint $table) {
            if (! Schema::hasColumn('club_sepa_notices', 'provider_status')) {
                $table->string('provider_status', 20)->nullable()->after('message_id');
            }

            if (! Schema::hasColumn('club_sepa_notices', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('provider_status');
            }

            if (! Schema::hasColumn('club_sepa_notices', 'bounced_at')) {
                $table->timestamp('bounced_at')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('club_sepa_notices', 'bounce_type')) {
                $table->string('bounce_type', 80)->nullable()->after('bounced_at');
            }

            if (! $this->indexExists('club_sepa_notices', 'club_sepa_notices_provider_status_updated_idx')) {
                $table->index(['provider_status', 'updated_at'], 'club_sepa_notices_provider_status_updated_idx');
            }
        });

        if (! Schema::hasTable('club_sepa_notice_provider_events')) {
            Schema::create('club_sepa_notice_provider_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_sepa_notice_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 40);
                $table->string('event_key', 64)->unique('club_sepa_notice_events_event_key_unique');
                $table->string('event_type', 40);
                $table->timestamp('occurred_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['club_sepa_notice_id', 'occurred_at'], 'club_sepa_notice_events_notice_occurred_idx');
            });
        } elseif (! $this->indexExists('club_sepa_notice_provider_events', 'club_sepa_notice_events_notice_occurred_idx')) {
            Schema::table('club_sepa_notice_provider_events', function (Blueprint $table) {
                $table->index(['club_sepa_notice_id', 'occurred_at'], 'club_sepa_notice_events_notice_occurred_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_notice_provider_events');

        Schema::table('club_sepa_notices', function (Blueprint $table) {
            $table->dropIndex('club_sepa_notices_provider_status_updated_idx');
            $table->dropColumn(['provider_status', 'delivered_at', 'bounced_at', 'bounce_type']);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
