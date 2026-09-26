<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_contribution_rules', function (Blueprint $table) {
            $table->foreignId('club_policy_document_id')
                ->nullable()
                ->after('club_membership_type_id')
                ->constrained('club_policy_documents')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_contribution_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_policy_document_id');
        });
    }
};
