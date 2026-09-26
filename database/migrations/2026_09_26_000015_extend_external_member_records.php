<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_external_members', function (Blueprint $table): void {
            $table->foreignId('club_membership_type_id')
                ->nullable()
                ->after('membership_status')
                ->constrained('club_membership_types')
                ->nullOnDelete();
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('country', 2)->nullable()->after('phone');
            $table->string('street')->nullable()->after('country');
            $table->string('house_number', 40)->nullable()->after('street');
            $table->string('postal_code', 30)->nullable()->after('house_number');
            $table->string('city')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('club_external_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('club_membership_type_id');
            $table->dropColumn([
                'phone',
                'country',
                'street',
                'house_number',
                'postal_code',
                'city',
            ]);
        });
    }
};
