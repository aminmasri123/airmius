<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('orderable');
            $table->string('type', 40);
            $table->string('provider', 30);
            $table->string('billing_interval', 20)->nullable();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->unsignedInteger('commission_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 30)->default('pending');
            $table->string('provider_checkout_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->text('checkout_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_checkout_id']);
            $table->index(['type', 'status']);
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_campaigns', 'target_url')) {
                $table->string('target_url')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('ad_campaigns', 'target_url')) {
                $table->dropColumn('target_url');
            }
        });

        Schema::dropIfExists('commerce_orders');
    }
};
