<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->string('borrower_kind', 20)->default('registered')->after('borrower_id');
            $table->foreignId('external_member_id')->nullable()->after('borrower_kind')->constrained('club_external_members')->nullOnDelete();
            $table->string('collateral_type', 30)->nullable()->after('rental_deposit_refunded_cents');
            $table->string('collateral_description')->nullable()->after('collateral_type');
            $table->unsignedInteger('collateral_amount_cents')->nullable()->after('collateral_description');
            $table->foreignId('collateral_received_by')->nullable()->after('collateral_amount_cents')->constrained('users')->nullOnDelete();
            $table->timestamp('collateral_received_at')->nullable()->after('collateral_received_by');
            $table->foreignId('collateral_returned_by')->nullable()->after('collateral_received_at')->constrained('users')->nullOnDelete();
            $table->timestamp('collateral_returned_at')->nullable()->after('collateral_returned_by');

            $table->index(['club_id', 'borrower_kind', 'status'], 'club_inventory_loans_borrower_kind_index');
            $table->index(['club_id', 'collateral_returned_at'], 'club_inventory_loans_collateral_open_index');
        });

        DB::table('club_inventory_loans')
            ->where('rental_type', 'external')
            ->update(['borrower_kind' => 'guest']);
    }

    public function down(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropIndex('club_inventory_loans_borrower_kind_index');
            $table->dropIndex('club_inventory_loans_collateral_open_index');
            $table->dropConstrainedForeignId('external_member_id');
            $table->dropConstrainedForeignId('collateral_received_by');
            $table->dropConstrainedForeignId('collateral_returned_by');
            $table->dropColumn([
                'borrower_kind',
                'collateral_type',
                'collateral_description',
                'collateral_amount_cents',
                'collateral_received_at',
                'collateral_returned_at',
            ]);
        });
    }
};
