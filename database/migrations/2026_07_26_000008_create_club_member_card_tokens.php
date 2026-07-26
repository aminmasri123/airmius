<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_member_card_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'user_id', 'expires_at']);
        });

        Schema::table('event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_participants', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('responded_at');
            }

            if (! Schema::hasColumn('event_participants', 'check_in_method')) {
                $table->string('check_in_method', 30)->nullable()->after('checked_in_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            foreach (['check_in_method', 'checked_in_at'] as $column) {
                if (Schema::hasColumn('event_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('club_member_card_tokens');
    }
};
