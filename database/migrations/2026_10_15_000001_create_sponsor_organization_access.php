<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('role', 40)->default('viewer');
            $table->string('status', 30)->default('pending');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->unique(['sponsor_id', 'email']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('sponsor_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sponsor_id', 'created_at']);
        });

        DB::table('sponsors')
            ->whereNotNull('owner_user_id')
            ->orderBy('id')
            ->eachById(function (object $sponsor): void {
                $email = DB::table('users')->where('id', $sponsor->owner_user_id)->value('email');
                if (! $email) {
                    return;
                }

                DB::table('sponsor_memberships')->updateOrInsert(
                    ['sponsor_id' => $sponsor->id, 'email' => mb_strtolower((string) $email)],
                    [
                        'user_id' => $sponsor->owner_user_id,
                        'role' => 'owner',
                        'status' => 'active',
                        'accepted_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_activity_logs');
        Schema::dropIfExists('sponsor_memberships');
    }
};
