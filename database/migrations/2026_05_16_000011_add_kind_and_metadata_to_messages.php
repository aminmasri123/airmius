<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'kind')) {
                $table->string('kind', 40)->default('user')->after('message');
            }

            if (! Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('kind');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('messages', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
