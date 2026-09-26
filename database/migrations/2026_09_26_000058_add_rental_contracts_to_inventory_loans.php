<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->json('rental_price_rules')->nullable()->after('booking_rules');
        });

        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->foreignId('borrower_id')->nullable()->change();
            $table->string('rental_type', 20)->default('internal')->after('booking_priority')->index();
            $table->string('external_renter_name')->nullable()->after('borrower_id');
            $table->string('external_renter_email')->nullable()->after('external_renter_name');
            $table->string('external_renter_phone')->nullable()->after('external_renter_email');
            $table->string('rental_contract_number')->nullable()->after('status');
            $table->unsignedInteger('rental_price_cents')->default(0)->after('rental_contract_number');
            $table->unsignedInteger('rental_deposit_cents')->default(0)->after('rental_price_cents');
            $table->unsignedInteger('rental_deposit_held_cents')->default(0)->after('rental_deposit_cents');
            $table->unsignedInteger('rental_deposit_refunded_cents')->default(0)->after('rental_deposit_held_cents');
            $table->unsignedInteger('rental_damage_claim_cents')->default(0)->after('rental_deposit_refunded_cents');
            $table->json('rental_price_snapshot')->nullable()->after('rental_damage_claim_cents');
            $table->json('handover_protocol')->nullable()->after('rental_price_snapshot');
            $table->json('return_protocol')->nullable()->after('handover_protocol');
            $table->foreignId('rental_invoice_id')->nullable()->after('return_protocol')->constrained('invoices')->nullOnDelete();

            $table->index(['club_id', 'rental_type', 'status'], 'club_inventory_loans_rental_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropIndex('club_inventory_loans_rental_status_index');
            $table->dropConstrainedForeignId('rental_invoice_id');
            $table->dropColumn([
                'rental_type',
                'external_renter_name',
                'external_renter_email',
                'external_renter_phone',
                'rental_contract_number',
                'rental_price_cents',
                'rental_deposit_cents',
                'rental_deposit_held_cents',
                'rental_deposit_refunded_cents',
                'rental_damage_claim_cents',
                'rental_price_snapshot',
                'handover_protocol',
                'return_protocol',
            ]);
            $table->foreignId('borrower_id')->nullable(false)->change();
        });

        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropColumn('rental_price_rules');
        });
    }
};
