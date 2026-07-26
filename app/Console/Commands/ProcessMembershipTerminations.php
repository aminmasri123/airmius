<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessMembershipTerminations extends Command
{
    protected $signature = 'airmius:process-membership-terminations
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Setzt abgelaufene Vereinsmitgliedschaften auf ehemalig und entfernt Teamzugriffe.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();
        $linked = 0;
        $external = 0;

        DB::table('club_user')
            ->where('membership_status', 'active')
            ->whereNull('membership_ended_at')
            ->whereNotNull('membership_ends_on')
            ->whereDate('membership_ends_on', '<=', $date->toDateString())
            ->orderBy('club_id')
            ->orderBy('user_id')
            ->cursor()
            ->each(function (object $membership) use (&$linked, $date): void {
                $club = Club::query()->find((int) $membership->club_id);
                $user = User::query()->find((int) $membership->user_id);
                if (! $club || ! $user) {
                    return;
                }

                DB::transaction(function () use ($club, $user, $date): void {
                    $teamIds = $club->teams()->pluck('id');
                    DB::table('team_user')
                        ->whereIn('team_id', $teamIds)
                        ->where('user_id', $user->id)
                        ->delete();

                    $club->users()->updateExistingPivot($user->id, [
                        'membership_status' => 'former',
                        'membership_ended_at' => $date,
                    ]);
                });

                AppNotification::send($user->id, 'club.membership_ended', [
                    'title' => 'Vereinsmitgliedschaft beendet',
                    'body' => 'Deine Mitgliedschaft bei '.$club->name.' ist beendet.',
                    'url' => route('auth.settings'),
                    'club_id' => $club->id,
                ]);

                foreach ($this->clubManagerRecipients($club) as $recipientId) {
                    AppNotification::send($recipientId, 'club.member_membership_ended', [
                        'title' => 'Mitgliedschaft beendet',
                        'body' => $user->name.' ist jetzt ehemaliges Mitglied.',
                        'url' => route('auth.club-memberships.index'),
                        'club_id' => $club->id,
                        'user_id' => $user->id,
                    ]);
                }

                $linked++;
            });

        ClubExternalMember::query()
            ->with('club:id,name,owner_id')
            ->where('membership_status', 'active')
            ->whereNull('membership_ended_at')
            ->whereNotNull('membership_ends_on')
            ->whereDate('membership_ends_on', '<=', $date->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($members) use (&$external, $date): void {
                foreach ($members as $member) {
                    $member->forceFill([
                        'membership_status' => 'former',
                        'membership_ended_at' => $date,
                    ])->save();

                    $name = $member->name ?: $member->email;
                    foreach ($this->clubManagerRecipients($member->club) as $recipientId) {
                        AppNotification::send($recipientId, 'club.external_member_membership_ended', [
                            'title' => 'Externe Mitgliedschaft beendet',
                            'body' => $name.' ist jetzt ehemaliges Mitglied.',
                            'url' => route('auth.club-memberships.index'),
                            'club_id' => $member->club_id,
                            'external_member_id' => $member->id,
                        ]);
                    }

                    $external++;
                }
            });

        $this->info("Vereinsmitgliedschaften beendet: {$linked} verknüpft, {$external} extern.");

        return self::SUCCESS;
    }

    private function clubManagerRecipients(Club $club): array
    {
        return $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
