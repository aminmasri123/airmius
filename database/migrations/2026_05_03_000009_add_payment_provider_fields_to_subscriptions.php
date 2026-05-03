<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('club_subscriptions', 'payment_provider')) {
                $table->string('payment_provider', 30)->nullable()->after('status');
            }

            if (! Schema::hasColumn('club_subscriptions', 'provider_subscription_id')) {
                $table->string('provider_subscription_id')->nullable()->after('payment_provider');
            }

            if (! Schema::hasColumn('club_subscriptions', 'provider_customer_id')) {
                $table->string('provider_customer_id')->nullable()->after('provider_subscription_id');
            }
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('user_subscriptions', 'payment_provider')) {
                $table->string('payment_provider', 30)->nullable()->after('status');
            }

            if (! Schema::hasColumn('user_subscriptions', 'provider_subscription_id')) {
                $table->string('provider_subscription_id')->nullable()->after('payment_provider');
            }

            if (! Schema::hasColumn('user_subscriptions', 'provider_customer_id')) {
                $table->string('provider_customer_id')->nullable()->after('provider_subscription_id');
            }
        });

        Schema::create('payment_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('billing_interval', 20)->default('monthly');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 30)->default('pending');
            $table->string('provider_checkout_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_checkout_id']);
            $table->index(['user_id', 'status']);
            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_checkouts');

        Schema::table('user_subscriptions', function (Blueprint $table) {
            foreach (['provider_customer_id', 'provider_subscription_id', 'payment_provider'] as $column) {
                if (Schema::hasColumn('user_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('club_subscriptions', function (Blueprint $table) {
            foreach (['provider_customer_id', 'provider_subscription_id', 'payment_provider'] as $column) {
                if (Schema::hasColumn('club_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
