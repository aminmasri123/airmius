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

Schedule::command('airmius:generate-recurring-contribution-invoices')
    ->dailyAt('07:30')
    ->withoutOverlapping();

Schedule::command('airmius:send-subscription-invoice-emails')
    ->dailyAt('08:15')
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

Schedule::command('airmius:send-learning-drip-notifications')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:monitor-learning-health')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:check-ai-provider-tokens')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('airmius:process-inactive-accounts')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('airmius:prune-ad-events')
    ->dailyAt('03:45')
    ->withoutOverlapping();

Schedule::command('airmius:prune-expired-stories')
    ->hourly()
    ->withoutOverlapping();
