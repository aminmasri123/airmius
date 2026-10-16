<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('pending_club_review');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('package_code', 120)->nullable();
            $table->json('rights_package')->nullable();
            $table->text('individual_offer_terms')->nullable();
            $table->string('contract_version', 80)->nullable();
            $table->unsignedSmallInteger('renewal_notice_days')->nullable();
            $table->date('renewal_deadline')->nullable();
            $table->unsignedBigInteger('budget_cents')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['sponsor_id', 'status']);
            $table->index(['club_id', 'status']);
        });

        DB::table('sponsors')
            ->whereNotNull('club_id')
            ->orderBy('id')
            ->chunkById(200, function ($sponsors): void {
                $now = now();
                DB::table('sponsor_partnerships')->insert($sponsors->map(fn ($sponsor) => [
                    'sponsor_id' => $sponsor->id,
                    'club_id' => $sponsor->club_id,
                    'created_by' => $sponsor->owner_user_id,
                    'status' => 'active',
                    'starts_at' => $sponsor->starts_at,
                    'ends_at' => $sponsor->ends_at,
                    'package_code' => $sponsor->package_code,
                    'rights_package' => $sponsor->rights_package,
                    'individual_offer_terms' => $sponsor->individual_offer_terms,
                    'contract_version' => $sponsor->contract_version,
                    'renewal_notice_days' => $sponsor->renewal_notice_days,
                    'renewal_deadline' => $sponsor->renewal_deadline,
                    'budget_cents' => $sponsor->amount === null ? null : (int) round(((float) $sponsor->amount) * 100),
                    'created_at' => $sponsor->created_at ?? $now,
                    'updated_at' => $sponsor->updated_at ?? $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_partnerships');
    }
};
