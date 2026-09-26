<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_sepa_notices', function (Blueprint $table) {
            $table->string('provider_status', 20)->nullable()->after('message_id');
            $table->timestamp('delivered_at')->nullable()->after('provider_status');
            $table->timestamp('bounced_at')->nullable()->after('delivered_at');
            $table->string('bounce_type', 80)->nullable()->after('bounced_at');
            $table->index(['provider_status', 'updated_at']);
        });

        Schema::create('club_sepa_notice_provider_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_sepa_notice_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('event_key', 64)->unique();
            $table->string('event_type', 40);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['club_sepa_notice_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_notice_provider_events');

        Schema::table('club_sepa_notices', function (Blueprint $table) {
            $table->dropIndex(['provider_status', 'updated_at']);
            $table->dropColumn(['provider_status', 'delivered_at', 'bounced_at', 'bounce_type']);
        });
    }
};
