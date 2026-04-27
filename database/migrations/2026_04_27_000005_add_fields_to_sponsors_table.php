<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('name')->after('club_id');
            $table->string('contact_name')->nullable()->after('name');
            $table->string('email')->nullable()->after('contact_name');
            $table->string('website')->nullable()->after('email');
            $table->string('logo')->nullable()->after('website');
            $table->decimal('amount', 10, 2)->nullable()->after('logo');
            $table->date('starts_at')->nullable()->after('amount');
            $table->date('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
            $table->dropColumn([
                'name',
                'contact_name',
                'email',
                'website',
                'logo',
                'amount',
                'starts_at',
                'ends_at',
            ]);
        });
    }
};
