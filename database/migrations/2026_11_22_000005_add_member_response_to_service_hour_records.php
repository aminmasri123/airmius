<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_service_hour_records', function (Blueprint $table): void {
            $table->string('member_response_status', 30)->default('pending')->after('status');
            $table->text('member_response_reason')->nullable()->after('member_response_status');
            $table->timestamp('member_responded_at')->nullable()->after('member_response_reason');
        });

        DB::table('club_service_hour_records')
            ->where('status', 'confirmed')
            ->update([
                'member_response_status' => 'accepted',
                'member_responded_at' => DB::raw('confirmed_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('club_service_hour_records', function (Blueprint $table): void {
            $table->dropColumn([
                'member_response_status',
                'member_response_reason',
                'member_responded_at',
            ]);
        });
    }
};
