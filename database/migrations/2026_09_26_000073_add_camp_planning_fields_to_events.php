<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            foreach ([
                'camp_groups', 'camp_supervision', 'camp_accommodation', 'camp_catering', 'camp_emergency_contacts',
            ] as $column) {
                if (! Schema::hasColumn('events', $column)) {
                    $table->json($column)->nullable()->after('participation_requirements');
                }
            }
            if (! Schema::hasColumn('events', 'camp_guardian_consent_required')) {
                $table->boolean('camp_guardian_consent_required')->default(false)->after('camp_emergency_contacts');
            }
            if (! Schema::hasColumn('events', 'camp_travel_consent_required')) {
                $table->boolean('camp_travel_consent_required')->default(false)->after('camp_guardian_consent_required');
            }
            if (! Schema::hasColumn('events', 'camp_privacy_notice_version')) {
                $table->string('camp_privacy_notice_version')->nullable()->after('camp_travel_consent_required');
            }
        });

        Schema::table('event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_participants', 'camp_group_key')) {
                $table->string('camp_group_key', 80)->nullable()->after('absence_reason');
            }
            if (! Schema::hasColumn('event_participants', 'camp_privacy_notice_accepted_at')) {
                $table->timestamp('camp_privacy_notice_accepted_at')->nullable()->after('camp_group_key');
            }
            if (! Schema::hasColumn('event_participants', 'camp_travel_consent_accepted_at')) {
                $table->timestamp('camp_travel_consent_accepted_at')->nullable()->after('camp_privacy_notice_accepted_at');
            }
            if (! Schema::hasColumn('event_participants', 'camp_guardian_consent_verified_at')) {
                $table->timestamp('camp_guardian_consent_verified_at')->nullable()->after('camp_travel_consent_accepted_at');
            }
            if (! Schema::hasColumn('event_participants', 'camp_emergency_contact_snapshot')) {
                $table->json('camp_emergency_contact_snapshot')->nullable()->after('camp_guardian_consent_verified_at');
            }
            if (! Schema::hasColumn('event_participants', 'camp_dietary_notes')) {
                $table->text('camp_dietary_notes')->nullable()->after('camp_emergency_contact_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            foreach (['camp_dietary_notes', 'camp_emergency_contact_snapshot', 'camp_guardian_consent_verified_at', 'camp_travel_consent_accepted_at', 'camp_privacy_notice_accepted_at', 'camp_group_key'] as $column) {
                if (Schema::hasColumn('event_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            foreach (['camp_privacy_notice_version', 'camp_travel_consent_required', 'camp_guardian_consent_required', 'camp_emergency_contacts', 'camp_catering', 'camp_accommodation', 'camp_supervision', 'camp_groups'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
