<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('package_code', 80)->nullable()->after('amount');
            $table->json('rights_package')->nullable()->after('package_code');
            $table->text('individual_offer_terms')->nullable()->after('rights_package');
            $table->string('contract_version', 80)->nullable()->after('individual_offer_terms');
            $table->unsignedInteger('renewal_notice_days')->nullable()->after('contract_version');
            $table->date('renewal_deadline')->nullable()->after('renewal_notice_days');
            $table->string('contract_approval_status', 30)->default('draft')->after('renewal_deadline');
            $table->foreignId('contract_submitted_by')->nullable()->after('contract_approval_status')->constrained('users')->nullOnDelete();
            $table->foreignId('contract_approved_by')->nullable()->after('contract_submitted_by')->constrained('users')->nullOnDelete();
            $table->timestamp('contract_approved_at')->nullable()->after('contract_approved_by');
            $table->index(['contract_approval_status', 'renewal_deadline'], 'sponsors_contract_renewal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropIndex('sponsors_contract_renewal_idx');
            $table->dropConstrainedForeignId('contract_approved_by');
            $table->dropConstrainedForeignId('contract_submitted_by');
            $table->dropColumn([
                'package_code',
                'rights_package',
                'individual_offer_terms',
                'contract_version',
                'renewal_notice_days',
                'renewal_deadline',
                'contract_approval_status',
                'contract_approved_at',
            ]);
        });
    }
};
