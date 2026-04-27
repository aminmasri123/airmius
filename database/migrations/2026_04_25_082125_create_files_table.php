<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
           $table->id();
        $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();
        $table->string('path');
        $table->string('type');
        $table->integer('size')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
