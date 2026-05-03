<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubSubscription;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\AppNotification;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendMembershipAndBillingReminders extends Command
{
    protected $signature = 'airmius:send-membership-billing-reminders
        {--membership-days=30 : Tage vor Ablauf einer Vereinsmitgliedschaft}
        {--invoice-days=7 : Tage vor Faelligkeit einer Rechnung}
        {--subscription-days=14 : Tage vor Ablauf eines Airmius-Abos}';

    protected $description = 'Benachrichtigt Vereine und Sportler ueber bald endende Mitgliedschaften, Abos und faellige Beitragszahlungen.';

    public function handle(): int
    {
        $membershipCount = $this->notifyExpiringClubMemberships((int) $this->option('membership-days'));
        $invoiceCount = $this->notifyDueInvoices((int) $this->option('invoice-days'));
        $subscriptionCount = $this->notifyExpiringSubscriptions((int) $this->option('subscription-days'));

        $this->info("Erinnerungen: {$membershipCount} Mitgliedschaften, {$invoiceCount} Rechnungen, {$subscriptionCount} Abos.");

        return self::SUCCESS;
    }

    private function notifyExpiringClubMemberships(int $days): int
    {
        $today = now()->startOfDay();
        $until = $today->copy()->addDays($days)->endOfDay();
        $sent = 0;

        DB::table('club_user')
            ->join('clubs', 'clubs.id', '=', 'club_user.club_id')
            ->join('users', 'users.id', '=', 'club_user.user_id')
            ->select([
                'club_user.club_id',
                'club_user.user_id',
                'club_user.member_number',
                'club_user.membership_ends_on',
                'clubs.name as club_name',
                'users.name as user_name',
            ])
            ->where('club_user.membership_status', 'active')
            ->whereNull('club_user.membership_end_notified_at')
            ->whereBetween('club_user.membership_ends_on', [$today->toDateString(), $until->toDateString()])
            ->orderBy('club_user.membership_ends_on')
            ->cursor()
            ->each(function ($membership) use (&$sent) {
                $date = $this->formatDate($membership->membership_ends_on);

                AppNotification::send((int) $membership->user_id, 'club.membership_ending_soon', [
                    'title' => 'Mitgliedschaft laeuft bald ab',
                    'body' => "Deine Mitgliedschaft bei {$membership->club_name} endet am {$date}.",
                    'url' => route('auth.settings'),
                    'club_id' => $membership->club_id,
                ]);

                foreach ($this->clubManagerRecipients((int) $membership->club_id) as $recipientId) {
                    AppNotification::send($recipientId, 'club.member_membership_ending_soon', [
                        'title' => 'Mitgliedschaft endet bald',
                        'body' => "{$membership->user_name} endet am {$date}.",
                        'url' => route('auth.club-memberships.index'),
                        'club_id' => $membership->club_id,
                        'user_id' => $membership->user_id,
                    ]);
                }

                DB::table('club_user')
                    ->where('club_id', $membership->club_id)
                    ->where('user_id', $membership->user_id)
                    ->update(['membership_end_notified_at' => now()]);

                $sent++;
            });

        ClubExternalMember::query()
            ->with('club:id,name,owner_id')
            ->where('membership_status', 'active')
            ->whereNull('membership_end_notified_at')
            ->whereBetween('membership_ends_on', [$today->toDateString(), $until->toDateString()])
            ->orderBy('membership_ends_on')
            ->chunkById(100, function ($externalMembers) use (&$sent) {
                foreach ($externalMembers as $externalMember) {
                    $date = $this->formatDate($externalMember->membership_ends_on);
                    $name = $externalMember->name ?: $externalMember->email;

                    foreach ($this->clubManagerRecipients($externalMember->club_id) as $recipientId) {
                        AppNotification::send($recipientId, 'club.external_member_membership_ending_soon', [
                            'title' => 'Externe Mitgliedschaft endet bald',
                            'body' => "{$name} endet am {$date}.",
                            'url' => route('auth.club-memberships.index'),
                            'club_id' => $externalMember->club_id,
                            'external_member_id' => $externalMember->id,
                        ]);
                    }

                    $externalMember->forceFill(['membership_end_notified_at' => now()])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    private function notifyDueInvoices(int $days): int
    {
        $today = now()->startOfDay();
        $until = $today->copy()->addDays($days)->endOfDay();
        $sent = 0;

        Invoice::query()
            ->with(['club:id,name,owner_id', 'user:id,name,email'])
            ->where('status', 'open')
            ->whereNull('due_soon_notified_at')
            ->whereBetween('due_date', [$today, $until])
            ->orderBy('due_date')
            ->chunkById(100, function ($invoices) use (&$sent) {
                foreach ($invoices as $invoice) {
                    $date = $this->formatDate($invoice->due_date);

                    if ($invoice->user_id) {
                        AppNotification::send((int) $invoice->user_id, 'invoice.due_soon', [
                            'title' => 'Beitragszahlung bald faellig',
                            'body' => "{$invoice->title} von {$invoice->club?->name} ist am {$date} faellig.",
                            'url' => route('auth.settings'),
                            'invoice_id' => $invoice->id,
                            'club_id' => $invoice->club_id,
                        ]);
                    }

                    foreach ($this->clubManagerRecipients((int) $invoice->club_id) as $recipientId) {
                        AppNotification::send($recipientId, 'club.invoice_due_soon', [
                            'title' => 'Beitrag bald faellig',
                            'body' => ($invoice->user?->name ?: 'Ein Mitglied')." hat {$invoice->title} am {$date} faellig.",
                            'url' => route('auth.club-memberships.index'),
                            'invoice_id' => $invoice->id,
                            'club_id' => $invoice->club_id,
                        ]);
                    }

                    $invoice->forceFill(['due_soon_notified_at' => now()])->save();
                    $sent++;
                }
            });

        Invoice::query()
            ->with(['club:id,name,owner_id', 'user:id,name,email'])
            ->where('status', 'open')
            ->whereNull('reminder_sent_at')
            ->where('due_date', '<', $today)
            ->orderBy('due_date')
            ->chunkById(100, function ($invoices) use (&$sent) {
                foreach ($invoices as $invoice) {
                    $date = $this->formatDate($invoice->due_date);

                    if ($invoice->user_id) {
                        AppNotification::send((int) $invoice->user_id, 'invoice.overdue', [
                            'title' => 'Beitragszahlung ist ueberfaellig',
                            'body' => "{$invoice->title} von {$invoice->club?->name} war am {$date} faellig.",
                            'url' => route('auth.settings'),
                            'invoice_id' => $invoice->id,
                            'club_id' => $invoice->club_id,
                        ]);
                    }

                    foreach ($this->clubManagerRecipients((int) $invoice->club_id) as $recipientId) {
                        AppNotification::send($recipientId, 'club.invoice_overdue', [
                            'title' => 'Beitrag ist ueberfaellig',
                            'body' => ($invoice->user?->name ?: 'Ein Mitglied')." ist seit {$date} mit {$invoice->title} offen.",
                            'url' => route('auth.club-memberships.index'),
                            'invoice_id' => $invoice->id,
                            'club_id' => $invoice->club_id,
                        ]);
                    }

                    $invoice->forceFill([
                        'status' => 'overdue',
                        'reminder_sent_at' => now(),
                    ])->save();

                    $sent++;
                }
            });

        return $sent;
    }

    private function notifyExpiringSubscriptions(int $days): int
    {
        $today = now()->startOfDay();
        $until = $today->copy()->addDays($days)->endOfDay();
        $sent = 0;

        ClubSubscription::query()
            ->with(['club:id,name,owner_id', 'plan:id,name'])
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->whereNull('renewal_notified_at')
            ->where(function ($query) use ($today, $until) {
                $query->whereBetween('current_period_ends_at', [$today, $until])
                    ->orWhere(function ($trialQuery) use ($today, $until) {
                        $trialQuery->whereNull('current_period_ends_at')
                            ->whereBetween('trial_ends_at', [$today, $until]);
                    });
            })
            ->chunkById(100, function ($subscriptions) use (&$sent) {
                foreach ($subscriptions as $subscription) {
                    $endsAt = $subscription->current_period_ends_at ?? $subscription->trial_ends_at;
                    $date = $this->formatDate($endsAt);

                    foreach ($this->clubManagerRecipients((int) $subscription->club_id) as $recipientId) {
                        AppNotification::send($recipientId, 'club.subscription_ending_soon', [
                            'title' => 'Airmius Vereinsplan laeuft bald ab',
                            'body' => "{$subscription->club?->name}: {$subscription->plan?->name} endet am {$date}.",
                            'url' => route('auth.club-memberships.index'),
                            'club_id' => $subscription->club_id,
                            'subscription_id' => $subscription->id,
                        ]);
                    }

                    $subscription->forceFill(['renewal_notified_at' => now()])->save();
                    $sent++;
                }
            });

        UserSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->whereNull('renewal_notified_at')
            ->where(function ($query) use ($today, $until) {
                $query->whereBetween('current_period_ends_at', [$today, $until])
                    ->orWhere(function ($trialQuery) use ($today, $until) {
                        $trialQuery->whereNull('current_period_ends_at')
                            ->whereBetween('trial_ends_at', [$today, $until]);
                    });
            })
            ->chunkById(100, function ($subscriptions) use (&$sent) {
                foreach ($subscriptions as $subscription) {
                    $endsAt = $subscription->current_period_ends_at ?? $subscription->trial_ends_at;
                    $date = $this->formatDate($endsAt);

                    AppNotification::send((int) $subscription->user_id, 'user.subscription_ending_soon', [
                        'title' => 'Dein Airmius Plan laeuft bald ab',
                        'body' => "{$subscription->plan?->name} endet am {$date}.",
                        'url' => route('guest.pricing'),
                        'subscription_id' => $subscription->id,
                    ]);

                    $subscription->forceFill(['renewal_notified_at' => now()])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    private function clubManagerRecipients(int $clubId): array
    {
        $club = Club::query()->select('id', 'owner_id')->find($clubId);

        if (! $club) {
            return [];
        }

        $recipients = $club->users()
            ->wherePivotIn('role', ['owner', 'admin', 'manager'])
            ->pluck('users.id')
            ->push($club->owner_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_map('intval', $recipients);
    }

    private function formatDate(CarbonInterface|string|null $value): string
    {
        if (! $value) {
            return '-';
        }

        return Carbon::parse($value)->format('d.m.Y');
    }
}
