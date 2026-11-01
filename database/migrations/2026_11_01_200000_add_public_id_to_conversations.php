<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->uuid('public_id')->nullable()->unique());
        DB::table('conversations')->select('id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('conversations')->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('public_id'));
    }
};
