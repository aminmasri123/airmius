<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table): void {
            $table->string('region', 120)->nullable()->after('country_code');
            $table->string('city', 120)->nullable()->after('region');
        });

        Schema::create('sponsor_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('subject', 160);
            $table->text('message');
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['sponsor_id', 'status', 'created_at']);
            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_inquiries');
        Schema::table('sponsors', function (Blueprint $table): void {
            $table->dropColumn(['region', 'city']);
        });
    }
};
