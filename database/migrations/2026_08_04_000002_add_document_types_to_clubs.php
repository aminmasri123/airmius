<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clubs', 'membership_application_document_types')) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->json('membership_application_document_types')->nullable()->after('membership_application_documents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clubs', 'membership_application_document_types')) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->dropColumn('membership_application_document_types');
            });
        }
    }
};
