<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_newsletter_subscriptions')) {
            Schema::create('club_newsletter_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_newsletter_subs_club_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained(indexName: 'club_newsletter_subs_user_fk')->nullOnDelete();
                $table->string('email');
                $table->string('name')->nullable();
                $table->string('status', 24)->default('pending');
                $table->string('confirmation_token_hash', 64)->nullable();
                $table->timestamp('confirmation_sent_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->string('unsubscribe_token_hash', 64)->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique('confirmation_token_hash', 'club_newsletter_subs_confirm_unique');
                $table->unique('unsubscribe_token_hash', 'club_newsletter_subs_unsub_unique');
                $table->unique(['club_id', 'email'], 'club_newsletter_subs_club_email_unique');
                $table->index(['club_id', 'status'], 'club_newsletter_subs_status_idx');
            });
        }

        if (! Schema::hasTable('club_newsletter_suppressions')) {
            Schema::create('club_newsletter_suppressions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_newsletter_supp_club_fk')->cascadeOnDelete();
                $table->string('email');
                $table->string('reason', 40);
                $table->timestamp('suppressed_at');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['club_id', 'email'], 'club_newsletter_supp_club_email_unique');
                $table->index(['club_id', 'reason'], 'club_newsletter_supp_reason_idx');
            });
        }

        if (! Schema::hasTable('club_newsletter_campaigns')) {
            Schema::create('club_newsletter_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_newsletter_campaigns_club_fk')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'club_newsletter_campaigns_creator_fk')->nullOnDelete();
                $table->string('subject');
                $table->text('body');
                $table->string('template_key')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->string('status', 24)->default('draft');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['club_id', 'idempotency_key'], 'club_newsletter_campaigns_idem_unique');
                $table->index(['club_id', 'status'], 'club_newsletter_campaigns_status_idx');
            });
        }

        if (! Schema::hasTable('club_newsletter_deliveries')) {
            Schema::create('club_newsletter_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_newsletter_campaign_id')->constrained(indexName: 'club_newsletter_delivs_campaign_fk')->cascadeOnDelete();
                $table->foreignId('club_newsletter_subscription_id')->nullable()->constrained(indexName: 'club_newsletter_delivs_sub_fk')->nullOnDelete();
                $table->foreignId('club_id')->constrained(indexName: 'club_newsletter_delivs_club_fk')->cascadeOnDelete();
                $table->string('email');
                $table->string('status', 24)->default('queued');
                $table->string('provider_message_id')->nullable();
                $table->string('bounce_type')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('bounced_at')->nullable();
                $table->timestamps();

                $table->unique(['club_newsletter_campaign_id', 'email'], 'club_newsletter_delivery_unique');
                $table->index(['club_id', 'status'], 'club_newsletter_delivs_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_newsletter_deliveries');
        Schema::dropIfExists('club_newsletter_campaigns');
        Schema::dropIfExists('club_newsletter_suppressions');
        Schema::dropIfExists('club_newsletter_subscriptions');
    }
};
