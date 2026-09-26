<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_number_allocations', function (Blueprint $table) {
            $table->string('assigned_subject_type', 40)->nullable()->after('allocation_key');
            $table->unsignedBigInteger('assigned_subject_id')->nullable()->after('assigned_subject_type');
            $table->index(
                ['club_id', 'assigned_subject_type', 'assigned_subject_id'],
                'club_number_allocations_subject_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('club_number_allocations', function (Blueprint $table) {
            $table->dropIndex('club_number_allocations_subject_lookup');
            $table->dropColumn(['assigned_subject_type', 'assigned_subject_id']);
        });
    }
};
