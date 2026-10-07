<?php

namespace App\Console\Commands;

use App\Models\ClubMembershipRequest;
use App\Services\ClubMembershipLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessScheduledMembershipTransitions extends Command
{
    protected $signature = 'airmius:process-scheduled-membership-transitions
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Wendet genehmigte Mitgliedschaftswechsel und Pausen am vorgesehenen Stichtag an.';

    public function handle(ClubMembershipLifecycleService $lifecycle): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : now()->startOfDay();
        $applied = 0;

        ClubMembershipRequest::query()
            ->where('status', 'approved')
            ->whereNull('applied_at')
            ->where(function ($query) use ($date) {
                $query->where(function ($changes) use ($date) {
                    $changes->where('type', 'membership_change')
                        ->whereDate('effective_on', '<=', $date);
                })->orWhere(function ($pauses) use ($date) {
                    $pauses->where('type', 'pause')
                        ->whereDate('requested_pause_from', '<=', $date);
                });
            })
            ->orderBy('id')
            ->eachById(function (ClubMembershipRequest $request) use ($lifecycle, $date, &$applied) {
                if ($lifecycle->applyScheduledTransition($request, $date)) {
                    $applied++;
                }
            });

        $resumed = DB::table('club_user')
            ->where('membership_status', 'paused')
            ->whereNotNull('paused_until')
            ->whereDate('paused_until', '<', $date)
            ->update([
                'membership_status' => 'active',
                'paused_from' => null,
                'paused_until' => null,
                'updated_at' => now(),
            ]);

        $this->info("Mitgliedschaftswechsel angewendet: {$applied}. Pausen beendet: {$resumed}.");

        return self::SUCCESS;
    }
}
