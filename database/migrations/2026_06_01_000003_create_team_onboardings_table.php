<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_onboardings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->boolean('team_structure_ready')->default(false);
            $table->boolean('roles_defined')->default(false);
            $table->boolean('calendar_setup_done')->default(false);
            $table->boolean('communication_setup_done')->default(false);
            $table->json('completed_steps')->nullable();
            $table->string('next_step')->nullable();
            $table->timestamps();

            $table->unique('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_onboardings');
    }
};

