<?php

namespace App\Jobs;

use App\Models\ClubSepaNotice;
use App\Services\ClubSepaNoticeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendClubSepaNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $noticeId) {}

    public function handle(ClubSepaNoticeService $service): void
    {
        $service->deliver($this->noticeId);
    }

    public function failed(?Throwable $exception): void
    {
        ClubSepaNotice::whereKey($this->noticeId)->where('status', 'sending')
            ->update(['status' => 'uncertain', 'error_code' => 'transport_uncertain']);
    }
}
