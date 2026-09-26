<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('sport_type', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'name']);
        });

        Schema::create('club_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('street')->nullable();
            $table->string('house_number', 40)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');
            $table->text('notes')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'name']);
        });

        Schema::create('club_training_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->nullOnDelete();
            $table->foreignId('club_location_id')->nullable()->constrained('club_locations')->nullOnDelete();
            $table->string('name', 160);
            $table->string('sport_type', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'name']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('club_department_id')->nullable()->after('club_id')->constrained('club_departments')->nullOnDelete();
            $table->foreignId('club_location_id')->nullable()->after('club_department_id')->constrained('club_locations')->nullOnDelete();
            $table->foreignId('club_training_group_id')->nullable()->after('club_location_id')->constrained('club_training_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_training_group_id');
            $table->dropConstrainedForeignId('club_location_id');
            $table->dropConstrainedForeignId('club_department_id');
        });

        Schema::dropIfExists('club_training_groups');
        Schema::dropIfExists('club_locations');
        Schema::dropIfExists('club_departments');
    }
};
