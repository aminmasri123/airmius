<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('idempotency_key', 64)->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('provider', 30);
            $table->string('provider_refund_id')->nullable();
            $table->string('status', 30)->default('processing');
            $table->text('reason')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->unsignedInteger('payout_impact_cents')->default(0);
            $table->string('payout_impact_status', 40)->default('not_applicable');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['commerce_order_id', 'status']);
            $table->index(['provider', 'provider_refund_id']);
        });

        Schema::table('commerce_return_requests', function (Blueprint $table) {
            $table->timestamp('restocked_at')->nullable()->after('refunded_at');
        });

        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->unsignedInteger('adjustment_cents')->default(0)->after('amount_cents');
            $table->unsignedInteger('recovery_cents')->default(0)->after('adjustment_cents');
            $table->string('reconciliation_status', 40)->default('none')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn(['adjustment_cents', 'recovery_cents', 'reconciliation_status']);
        });

        Schema::table('commerce_return_requests', function (Blueprint $table) {
            $table->dropColumn('restocked_at');
        });

        Schema::dropIfExists('commerce_refunds');
    }
};
