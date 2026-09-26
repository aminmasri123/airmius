<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_newsletter_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('confirmation_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('unsubscribe_token_hash', 64)->nullable()->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'email']);
            $table->index(['club_id', 'status']);
        });

        Schema::create('club_newsletter_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('reason', 40);
            $table->timestamp('suppressed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'email']);
            $table->index(['club_id', 'reason']);
        });

        Schema::create('club_newsletter_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('template_key')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'idempotency_key']);
            $table->index(['club_id', 'status']);
        });

        Schema::create('club_newsletter_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_newsletter_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('status', 24)->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->string('bounce_type')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamps();

            $table->unique(['club_newsletter_campaign_id', 'email'], 'club_newsletter_delivery_unique');
            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_newsletter_deliveries');
        Schema::dropIfExists('club_newsletter_campaigns');
        Schema::dropIfExists('club_newsletter_suppressions');
        Schema::dropIfExists('club_newsletter_subscriptions');
    }
};
