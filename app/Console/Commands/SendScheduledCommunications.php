<?php

namespace App\Console\Commands;

use App\Services\ScheduledCommunicationService;
use Illuminate\Console\Command;

class SendScheduledCommunications extends Command
{
    protected $signature = 'airmius:send-scheduled-communications {--limit=100}';

    protected $description = 'Send due scheduled communication templates.';

    public function handle(ScheduledCommunicationService $service): int
    {
        $sent = $service->sendDue((int) $this->option('limit'));

        $this->info($sent.' geplante Kommunikationssendungen verarbeitet.');

        return self::SUCCESS;
    }
}
