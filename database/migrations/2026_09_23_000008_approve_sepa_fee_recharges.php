<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_sepa_fee_recharges', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('revenue_account', 20)->nullable();
            $table->boolean('review_required')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('club_sepa_fee_recharges', function (Blueprint $table) {
            $table->dropUnique(['invoice_id']);
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'revenue_account', 'review_required']);
        });
    }
};
