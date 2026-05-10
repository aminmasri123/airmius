<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_campaigns', 'review_note')) {
                $table->text('review_note')->nullable()->after('status');
            }

            if (! Schema::hasColumn('ad_campaigns', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_note');
            }
        });

        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('objective', 40)->nullable();
            $table->string('placement', 40)->nullable();
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('value_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('session_hash', 128)->nullable();
            $table->string('ip_hash', 128)->nullable();
            $table->string('user_agent_hash', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['ad_campaign_id', 'event_type', 'occurred_at']);
            $table->index(['session_hash', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');

        Schema::table('ad_campaigns', function (Blueprint $table) {
            foreach (['review_note', 'reviewed_at'] as $column) {
                if (Schema::hasColumn('ad_campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
