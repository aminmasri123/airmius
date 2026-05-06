<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('folder_id');
        });

        DB::table('files')
            ->select(['id', 'path'])
            ->orderBy('id')
            ->chunkById(200, function ($files) {
                foreach ($files as $file) {
                    $path = str_replace('\\', '/', (string) $file->path);

                    DB::table('files')
                        ->where('id', $file->id)
                        ->update(['display_name' => basename($path)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
