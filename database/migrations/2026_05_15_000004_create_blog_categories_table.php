<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $categories = [
            'Airmius News',
            'Vereine',
            'Training',
            'Digitalisierung',
            'Sponsoring',
            'Marketplace',
            'E-Learning',
            'Events',
            'Community',
            'Recht und Sicherheit',
        ];

        foreach ($categories as $index => $name) {
            DB::table('blog_categories')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
