<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_seller_applications', function (Blueprint $table) {
            $table->string('verification_version', 20)->nullable()->after('accepted_rules');
            $table->json('verification_snapshot')->nullable()->after('verification_version');
        });

        Schema::table('payout_profiles', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('tax_number');
            $table->string('tax_status', 30)->nullable()->after('country_code');
            $table->boolean('beneficial_owner_confirmed')->default(false)->after('tax_status');
            $table->string('terms_version', 20)->nullable()->after('beneficial_owner_confirmed');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
            $table->foreignId('verified_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('rejection_reason')->nullable()->after('verified_at');
            $table->index(['status', 'updated_at'], 'payout_profiles_review_idx');
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('country_code', 2)->nullable()->after('legal_name');
            $table->string('registration_number', 120)->nullable()->after('country_code');
            $table->string('vat_id', 80)->nullable()->after('registration_number');
            $table->json('accepted_rules')->nullable()->after('vat_id');
            $table->string('verification_version', 20)->nullable()->after('accepted_rules');
            $table->string('verification_status', 30)->default('verified')->after('verification_version');
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->timestamp('verification_requested_at')->nullable()->after('verification_note');
            $table->foreignId('verified_by')->nullable()->after('verification_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->index(['verification_status', 'ends_at'], 'sponsors_public_trust_idx');
            $table->index(['owner_user_id', 'verification_status'], 'sponsors_owner_trust_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropIndex('sponsors_public_trust_idx');
            $table->dropIndex('sponsors_owner_trust_idx');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'legal_name',
                'country_code',
                'registration_number',
                'vat_id',
                'accepted_rules',
                'verification_version',
                'verification_status',
                'verification_note',
                'verification_requested_at',
                'verified_at',
            ]);
        });

        Schema::table('payout_profiles', function (Blueprint $table) {
            $table->dropIndex('payout_profiles_review_idx');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'country_code',
                'tax_status',
                'beneficial_owner_confirmed',
                'terms_version',
                'terms_accepted_at',
                'verified_at',
                'rejection_reason',
            ]);
        });

        Schema::table('marketplace_seller_applications', function (Blueprint $table) {
            $table->dropColumn(['verification_version', 'verification_snapshot']);
        });
    }
};
