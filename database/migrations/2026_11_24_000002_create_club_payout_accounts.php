<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('club_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('holder');
            $table->text('iban');
            $table->string('last_four', 4);
            $table->string('status')->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('commerce_order_items', function (Blueprint $table) {
            $table->unsignedInteger('club_commission_cents')->nullable();
            $table->timestamp('club_delivered_at')->nullable();
            $table->foreignId('club_payout_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('club_payout_cents')->nullable();
            $table->timestamp('club_paid_at')->nullable();
            $table->string('club_bank_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commerce_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_payout_account_id');
            $table->dropColumn(['club_commission_cents', 'club_delivered_at', 'club_payout_cents', 'club_paid_at', 'club_bank_reference']);
        });
        Schema::dropIfExists('club_payout_accounts');
    }
};
