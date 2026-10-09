<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('donor_type', 30)->nullable();
            $table->json('donor_snapshot')->nullable();
            $table->foreignId('club_business_partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_business_partner_id');
            $table->dropConstrainedForeignId('sponsor_id');
            $table->dropColumn(['donor_type', 'donor_snapshot']);
        });
    }
};
