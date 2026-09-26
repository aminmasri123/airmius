<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_role_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 20)->default('club');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_key', 64)->default('club');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['club_role_definition_id', 'user_id', 'scope_key'], 'club_role_assignments_role_user_scope_unique');
            $table->index(['club_id', 'user_id', 'scope_type', 'scope_id'], 'club_role_assignments_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_role_assignments');
    }
};
