<?php

use App\Support\SupportedLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_courses', function (Blueprint $table) {
            $table->uuid('translation_group')->nullable()->after('language');
            $table->index(
                ['language', 'status', 'is_public', 'published_at', 'id'],
                'learning_courses_locale_public_index',
            );
        });

        DB::table('learning_courses')
            ->select(['id', 'language'])
            ->orderBy('id')
            ->chunkById(500, function ($courses): void {
                foreach ($courses as $course) {
                    DB::table('learning_courses')
                        ->where('id', $course->id)
                        ->update([
                            'language' => SupportedLocale::normalize($course->language)
                                ?? SupportedLocale::DEFAULT,
                            'translation_group' => (string) Str::uuid(),
                        ]);
                }
            });

        Schema::table('learning_courses', function (Blueprint $table) {
            $table->unique(
                ['translation_group', 'language'],
                'learning_courses_translation_locale_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('learning_courses', function (Blueprint $table) {
            $table->dropUnique('learning_courses_translation_locale_unique');
            $table->dropIndex('learning_courses_locale_public_index');
            $table->dropColumn('translation_group');
        });
    }
};
