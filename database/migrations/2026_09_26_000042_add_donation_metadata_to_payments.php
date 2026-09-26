<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'donation_type')) {
                $table->string('donation_type', 40)->nullable()->after(
                    Schema::hasColumn('payments', 'donation_number') ? 'donation_number' : 'reference'
                );
            }

            if (! Schema::hasColumn('payments', 'donation_restriction')) {
                $table->string('donation_restriction')->nullable()->after('donation_type');
            }

            if (! Schema::hasColumn('payments', 'donation_campaign')) {
                $table->string('donation_campaign')->nullable()->after('donation_restriction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['donation_campaign', 'donation_restriction', 'donation_type'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
