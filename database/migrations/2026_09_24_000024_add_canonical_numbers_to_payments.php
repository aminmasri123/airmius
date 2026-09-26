<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->after('reference');
            $table->string('donation_number')->nullable()->after('receipt_number');
            $table->unique(['club_id', 'receipt_number'], 'payments_club_receipt_number_unique');
            $table->unique(['club_id', 'donation_number'], 'payments_club_donation_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_club_receipt_number_unique');
            $table->dropUnique('payments_club_donation_number_unique');
            $table->dropColumn(['receipt_number', 'donation_number']);
        });
    }
};
