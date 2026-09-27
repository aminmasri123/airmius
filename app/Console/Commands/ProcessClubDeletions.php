<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Services\ClubDataErasureService;
use App\Services\ClubDeletionService;
use Illuminate\Console\Command;
use Throwable;

class ProcessClubDeletions extends Command
{
    protected $signature = 'airmius:process-club-deletions';

    protected $description = 'Process club deletion requests after the 30-day cancellation period';

    public function handle(ClubDeletionService $service, ClubDataErasureService $erasure): int
    {
        $failed = false;
        Club::whereNotNull('deletion_scheduled_at')->select('id')->chunkById(100, function ($clubs) use ($service, &$failed) {
            foreach ($clubs as $club) {
                try {
                    $service->process($club->id);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error('Club '.$club->id.': deletion failed; will retry.');
                    $failed = true;
                }
            }
        });
        try {
            $erasure->cleanupFiles();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Stored-file cleanup failed; will retry.');
            $failed = true;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
