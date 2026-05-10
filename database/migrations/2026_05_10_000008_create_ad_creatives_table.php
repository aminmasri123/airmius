<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Variante A');
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->text('primary_text')->nullable();
            $table->string('target_url')->nullable();
            $table->string('cta_label', 80)->nullable();
            $table->string('creative_format', 40)->default('feed_square');
            $table->string('creative_image_path')->nullable();
            $table->string('creative_image_url')->nullable();
            $table->unsignedSmallInteger('weight')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('spent_cents')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['ad_campaign_id', 'is_active']);
        });

        Schema::table('ad_events', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_events', 'ad_creative_id')) {
                $table->foreignId('ad_creative_id')->nullable()->after('ad_campaign_id')->constrained('ad_creatives')->nullOnDelete();
                $table->index(['ad_creative_id', 'event_type', 'occurred_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            if (Schema::hasColumn('ad_events', 'ad_creative_id')) {
                $table->dropConstrainedForeignId('ad_creative_id');
            }
        });

        Schema::dropIfExists('ad_creatives');
    }
};
