<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('competition_registrations', 'nominated_at')) {
                $table->timestamp('nominated_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('competition_registrations', 'nomination_confirmed_at')) {
                $table->timestamp('nomination_confirmed_at')->nullable()->after('nominated_at');
            }
            if (! Schema::hasColumn('competition_registrations', 'eligibility_checked_at')) {
                $table->timestamp('eligibility_checked_at')->nullable()->after('confirmed_at');
            }
            if (! Schema::hasColumn('competition_registrations', 'license_status')) {
                $table->string('license_status')->default('unchecked')->after('eligibility_checked_at');
            }
            if (! Schema::hasColumn('competition_registrations', 'start_fee_cents')) {
                $table->unsignedInteger('start_fee_cents')->default(0)->after('license_status');
            }
            if (! Schema::hasColumn('competition_registrations', 'start_fee_paid_at')) {
                $table->timestamp('start_fee_paid_at')->nullable()->after('start_fee_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_registrations', function (Blueprint $table): void {
            foreach ([
                'start_fee_paid_at',
                'start_fee_cents',
                'license_status',
                'eligibility_checked_at',
                'nomination_confirmed_at',
                'nominated_at',
            ] as $column) {
                if (Schema::hasColumn('competition_registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
