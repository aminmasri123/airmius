<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('airmius:send-membership-billing-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('airmius:process-membership-terminations')
    ->dailyAt('08:05')
    ->withoutOverlapping();

Schedule::command('airmius:generate-recurring-contribution-invoices')
    ->dailyAt('07:30')
    ->withoutOverlapping();

Schedule::command('airmius:send-subscription-invoice-emails')
    ->dailyAt('08:15')
    ->withoutOverlapping();

Schedule::command('airmius:send-notification-digests')
    ->dailyAt('18:00')
    ->withoutOverlapping();

Schedule::command('airmius:process-subscription-lifecycle')
    ->dailyAt('08:45')
    ->withoutOverlapping();

Schedule::command('airmius:sync-sport-integrations')
    ->twiceDaily(6, 18)
    ->withoutOverlapping();

Schedule::command('airmius:prepare-outfit-deliveries')
    ->dailyAt('08:30')
    ->withoutOverlapping();

Schedule::command('airmius:process-outfit-subscription-lifecycle')
    ->dailyAt('08:40')
    ->withoutOverlapping();

Schedule::command('airmius:send-outfit-payment-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('airmius:send-event-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('airmius:send-sport-matching-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('airmius:mobile-push-dispatch --limit=500')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('airmius:send-learning-drip-notifications')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:monitor-learning-health')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:monitor-operations')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:check-ai-provider-tokens')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('airmius:process-inactive-accounts')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('airmius:backup-database')
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::command('airmius:prune-ad-events --limit=1000')
    ->dailyAt('03:45')
    ->withoutOverlapping();

Schedule::command('airmius:prune-website-requests --limit=1000')
    ->dailyAt('03:48')
    ->withoutOverlapping();

Schedule::command('airmius:prune-recruiting-interests --limit=1000')
    ->dailyAt('03:50')
    ->withoutOverlapping();

Schedule::command('airmius:prune-expired-stories --limit=500')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:dispatch-domain-outbox --limit=500')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('airmius:prune-platform-delivery --outbox-days=30 --failed-outbox-days=90 --limit=1000')
    ->dailyAt('03:55')
    ->withoutOverlapping();
