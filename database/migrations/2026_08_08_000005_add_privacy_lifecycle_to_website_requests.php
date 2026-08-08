<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            $table->timestamp('consent_at')->nullable()->after('notes')->index();
            $table->timestamp('status_changed_at')->nullable()->after('consent_at');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('retention_expires_at')->nullable()->after('status_changed_by')->index();
        });

        DB::table('website_requests')
            ->select(['id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    $createdAt = Carbon::parse($row->created_at);
                    DB::table('website_requests')->where('id', $row->id)->update([
                        'consent_at' => $createdAt,
                        'status_changed_at' => Carbon::parse($row->updated_at ?? $row->created_at),
                        'retention_expires_at' => $createdAt->copy()->addYear(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn(['consent_at', 'status_changed_at', 'retention_expires_at']);
        });
    }
};
