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
