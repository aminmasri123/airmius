<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'registration_audience')) {
                $table->string('registration_audience')->default('members_and_guests')->after('max_participants');
            }
            if (! Schema::hasColumn('events', 'participation_requirements')) {
                $table->json('participation_requirements')->nullable()->after('registration_audience');
            }
            if (! Schema::hasColumn('events', 'participation_consent_required')) {
                $table->boolean('participation_consent_required')->default(false)->after('participation_requirements');
            }
            if (! Schema::hasColumn('events', 'participation_consent_version')) {
                $table->string('participation_consent_version')->nullable()->after('participation_consent_required');
            }
            if (! Schema::hasColumn('events', 'member_price_cents')) {
                $table->unsignedInteger('member_price_cents')->default(0)->after('participation_consent_version');
            }
            if (! Schema::hasColumn('events', 'guest_price_cents')) {
                $table->unsignedInteger('guest_price_cents')->default(0)->after('member_price_cents');
            }
            if (! Schema::hasColumn('events', 'waitlist_offer_ttl_minutes')) {
                $table->unsignedInteger('waitlist_offer_ttl_minutes')->nullable()->after('guest_price_cents');
            }
        });

        Schema::table('event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_participants', 'waitlist_position')) {
                $table->unsignedInteger('waitlist_position')->nullable()->after('responded_at');
            }
            if (! Schema::hasColumn('event_participants', 'waitlist_promoted_at')) {
                $table->timestamp('waitlist_promoted_at')->nullable()->after('waitlist_position');
            }
            if (! Schema::hasColumn('event_participants', 'waitlist_offer_expires_at')) {
                $table->timestamp('waitlist_offer_expires_at')->nullable()->after('waitlist_promoted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            foreach (['waitlist_offer_expires_at', 'waitlist_promoted_at', 'waitlist_position'] as $column) {
                if (Schema::hasColumn('event_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            foreach ([
                'waitlist_offer_ttl_minutes',
                'guest_price_cents',
                'member_price_cents',
                'participation_consent_version',
                'participation_consent_required',
                'participation_requirements',
                'registration_audience',
            ] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
