<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Services\SupportInboxNotificationService;

class SupportTicketObserver
{
    public function created(SupportTicket $ticket): void
    {
        app(SupportInboxNotificationService::class)->created($ticket);
    }

    public function updated(SupportTicket $ticket): void
    {
        if ($ticket->wasChanged('assigned_to')) {
            app(SupportInboxNotificationService::class)->assigned($ticket);
        }
    }
}
