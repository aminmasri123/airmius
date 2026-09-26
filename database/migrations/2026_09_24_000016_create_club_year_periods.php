<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_year_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 160);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['club_id', 'type', 'name']);
            $table->index(['club_id', 'type', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_year_periods');
    }
};
