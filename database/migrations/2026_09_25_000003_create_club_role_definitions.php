<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_role_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name', 120);
            $table->json('permissions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['club_id', 'key']);
            $table->index(['club_id', 'is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_role_definitions');
    }
};
