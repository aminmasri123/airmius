<?php

use App\Services\SupportSlaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('club_id')->nullable()->after('user_id')->constrained('clubs')->nullOnDelete();
            $table->unsignedInteger('response_sla_target_minutes')->nullable()->after('last_reply_at');
            $table->timestamp('response_due_at')->nullable()->after('response_sla_target_minutes');
            $table->timestamp('first_response_at')->nullable()->after('response_due_at');
            $table->unsignedInteger('sla_target_minutes')->nullable()->after('first_response_at');
            $table->string('sla_policy_version', 80)->nullable()->after('sla_target_minutes');

            $table->index(['club_id', 'status', 'due_at'], 'support_tickets_club_sla_index');
            $table->index(['status', 'response_due_at'], 'support_tickets_response_sla_index');
        });

        $sla = app(SupportSlaService::class);
        DB::table('support_tickets')
            ->select(['id', 'priority', 'created_at', 'due_at'])
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use ($sla): void {
                foreach ($tickets as $ticket) {
                    $startedAt = $ticket->created_at ? Carbon::parse($ticket->created_at) : now();
                    $attributes = $sla->initialAttributes((string) $ticket->priority, $startedAt);
                    if ($ticket->due_at) {
                        $attributes['due_at'] = $ticket->due_at;
                    }

                    DB::table('support_tickets')->where('id', $ticket->id)->update($attributes);
                }
            });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_club_sla_index');
            $table->dropIndex('support_tickets_response_sla_index');
            $table->dropConstrainedForeignId('club_id');
            $table->dropColumn([
                'response_sla_target_minutes',
                'response_due_at',
                'first_response_at',
                'sla_target_minutes',
                'sla_policy_version',
            ]);
        });
    }
};
