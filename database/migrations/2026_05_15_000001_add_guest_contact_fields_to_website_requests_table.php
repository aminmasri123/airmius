<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            if (Schema::hasColumn('website_requests', 'user_id')) {
                $table->foreignId('user_id')->nullable()->change();
            }

            if (! Schema::hasColumn('website_requests', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('club_id');
            }

            if (! Schema::hasColumn('website_requests', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }

            if (! Schema::hasColumn('website_requests', 'guest_phone')) {
                $table->string('guest_phone')->nullable()->after('guest_email');
            }

            if (! Schema::hasColumn('website_requests', 'club_name')) {
                $table->string('club_name')->nullable()->after('guest_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            foreach (['guest_name', 'guest_email', 'guest_phone', 'club_name'] as $column) {
                if (Schema::hasColumn('website_requests', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('website_requests', 'user_id')) {
                $table->foreignId('user_id')->nullable(false)->change();
            }
        });
    }
};
