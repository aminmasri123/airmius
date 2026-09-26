<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_membership_requests', 'effective_on')) {
                $table->date('effective_on')->nullable()->after('requested_termination_on');
            }
            if (! Schema::hasColumn('club_membership_requests', 'preview_snapshot')) {
                $table->json('preview_snapshot')->nullable()->after('preview_interval');
            }
            if (! Schema::hasColumn('club_membership_requests', 'is_exception')) {
                $table->boolean('is_exception')->default(false)->after('preview_snapshot');
            }
            if (! Schema::hasColumn('club_membership_requests', 'exception_reason')) {
                $table->text('exception_reason')->nullable()->after('is_exception');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table): void {
            foreach (['exception_reason', 'is_exception', 'preview_snapshot', 'effective_on'] as $column) {
                if (Schema::hasColumn('club_membership_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
