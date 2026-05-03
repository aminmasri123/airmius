<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->timestamp('invoice_email_sent_at')->nullable()->after('paid_at');
            $table->timestamp('payment_confirmation_email_sent_at')->nullable()->after('invoice_email_sent_at');
            $table->timestamp('reminder_email_sent_at')->nullable()->after('payment_confirmation_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_email_sent_at',
                'payment_confirmation_email_sent_at',
                'reminder_email_sent_at',
            ]);
        });
    }
};
