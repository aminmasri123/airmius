<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('post_type')->default('normal')->after('visibility');
            $table->foreignId('sport_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
        });

        Schema::create('post_sport_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_skill_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'sport_skill_id']);
        });

        Schema::create('post_helpfuls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context')->default('helpful');
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_helpfuls');
        Schema::dropIfExists('post_sport_skill');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn('post_type');
        });
    }
};
