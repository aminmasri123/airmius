<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->timestamp('issued_at')->nullable()->after('checked_out_at');
            $table->timestamp('lost_at')->nullable()->after('returned_at');
            $table->timestamp('damaged_at')->nullable()->after('lost_at');
            $table->foreignId('responsibility_transferred_to')->nullable()->after('returned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('responsibility_transferred_at')->nullable()->after('responsibility_transferred_to');
            $table->text('damage_description')->nullable()->after('return_condition');
        });

        Schema::table('club_access_handover_reviews', function (Blueprint $table): void {
            $table->json('inventory_loan_snapshot')->nullable()->after('delegation_count');
        });
    }

    public function down(): void
    {
        Schema::table('club_access_handover_reviews', function (Blueprint $table): void {
            $table->dropColumn('inventory_loan_snapshot');
        });

        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsibility_transferred_to');
            $table->dropColumn([
                'issued_at',
                'lost_at',
                'damaged_at',
                'responsibility_transferred_at',
                'damage_description',
            ]);
        });
    }
};
