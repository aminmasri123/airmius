<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionLineup;
use App\Models\CompetitionOfficialAssignment;
use App\Models\CompetitionPlayingTimeEntry;
use App\Models\CompetitionRegistration;
use App\Models\CompetitionRelayTeam;
use App\Models\CompetitionRosterEntry;
use App\Models\CompetitionStartListEntry;
use App\Models\CompetitionSubstitution;
use App\Models\CompetitionTournamentCorrection;
use App\Models\CompetitionTournamentGroup;
use App\Models\CompetitionTournamentMatch;
use App\Models\CompetitionTournamentStanding;
use App\Models\CompetitionVenue;
use App\Models\Event;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetitionSportPlanningService
{
    /** @param array<int, int> $rosterEntryIds */
    public function generateTournament(Competition $competition, User $actor, array $rosterEntryIds, array $options = []): array
    {
        $this->authorize($competition->club, $actor);

        return DB::transaction(function () use ($competition, $actor, $rosterEntryIds, $options): array {
            $classId = $options['competition_class_id'] ?? null;
            $groupCount = max(1, (int) ($options['group_count'] ?? 1));
            $entries = CompetitionRosterEntry::query()
                ->where('competition_id', $competition->id)
                ->where('club_id', $competition->club_id)
                ->when($classId, fn ($query) => $query->where('competition_class_id', $classId))
                ->whereIn('id', array_values(array_unique($rosterEntryIds)))
                ->get()
                ->sortBy(fn (CompetitionRosterEntry $entry) => sprintf('%05d-%s-%05d', $entry->metadata['seed'] ?? 99999, mb_strtolower((string) $entry->display_name), $entry->id))
                ->values();

            if ($entries->count() !== count(array_unique($rosterEntryIds))) {
                throw ValidationException::withMessages(['roster_entries' => __('validation.exists', ['attribute' => 'roster_entries'])]);
            }

            CompetitionTournamentCorrection::query()->create([
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'actor_id' => $actor->id,
                'correction_type' => 'generate_tournament',
                'before' => [
                    'groups' => CompetitionTournamentGroup::query()->where('competition_id', $competition->id)->count(),
                    'matches' => CompetitionTournamentMatch::query()->where('competition_id', $competition->id)->count(),
                ],
                'after' => ['participant_count' => $entries->count(), 'group_count' => $groupCount],
                'status' => 'applied',
            ]);

            CompetitionTournamentMatch::query()->where('competition_id', $competition->id)->delete();
            CompetitionTournamentStanding::query()->where('competition_id', $competition->id)->delete();
            CompetitionTournamentGroup::query()->where('competition_id', $competition->id)->delete();

            $groups = collect(range(1, $groupCount))->map(function (int $number) use ($competition, $classId): CompetitionTournamentGroup {
                return CompetitionTournamentGroup::query()->create([
                    'club_id' => $competition->club_id,
                    'competition_id' => $competition->id,
                    'competition_class_id' => $classId,
                    'name' => 'Gruppe '.chr(64 + $number),
                    'sort_order' => $number,
                    'metadata' => [],
                ]);
            });

            foreach ($entries as $index => $entry) {
                $group = $groups[$index % $groupCount];
                $group->entries()->create([
                    'club_id' => $competition->club_id,
                    'competition_id' => $competition->id,
                    'competition_roster_entry_id' => $entry->id,
                    'display_name' => $entry->display_name ?: 'Starter '.$entry->id,
                    'seed' => $entry->metadata['seed'] ?? ($index + 1),
                    'metadata' => [],
                ]);
            }

            $matchNumber = 1;
            foreach ($groups as $group) {
                $matchNumber = $this->createRoundRobinMatches($competition, $group->fresh('entries'), $classId, $matchNumber);
            }

            $this->generateKnockoutRound($competition, $actor, [
                'competition_class_id' => $classId,
                'qualifiers_per_group' => $options['qualifiers_per_group'] ?? 2,
            ]);

            return [
                'groups' => $competition->tournamentGroups()->with('entries')->orderBy('sort_order')->get(),
                'matches' => $competition->tournamentMatches()->orderBy('phase')->orderBy('round_number')->orderBy('match_number')->get(),
            ];
        });
    }

    public function applyMatchCorrection(Competition $competition, User $actor, CompetitionTournamentMatch $match, array $changes, ?string $reason = null): CompetitionTournamentCorrection
    {
        $this->authorize($competition->club, $actor);

        if ((int) $match->competition_id !== (int) $competition->id || (int) $match->club_id !== (int) $competition->club_id) {
            throw ValidationException::withMessages(['match' => __('validation.exists', ['attribute' => 'match'])]);
        }

        return DB::transaction(function () use ($competition, $actor, $match, $changes, $reason): CompetitionTournamentCorrection {
            $before = $match->only(['home_score', 'away_score', 'status', 'scheduled_at', 'event_id', 'metadata']);
            $allowed = array_intersect_key($changes, array_flip(['home_score', 'away_score', 'status', 'scheduled_at', 'event_id', 'metadata']));
            $after = array_merge($before, $allowed);
            $conflicts = $this->detectMatchConflicts($match, $after);

            if ($conflicts !== []) {
                throw ValidationException::withMessages(['conflicts' => $conflicts]);
            }

            $match->forceFill($allowed)->save();

            $correction = CompetitionTournamentCorrection::query()->create([
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'competition_tournament_match_id' => $match->id,
                'actor_id' => $actor->id,
                'correction_type' => 'match_update',
                'before' => $before,
                'after' => $match->fresh()->only(['home_score', 'away_score', 'status', 'scheduled_at', 'event_id', 'metadata']),
                'conflicts' => [],
                'status' => 'applied',
                'reason' => $reason,
            ]);

            if ($match->competition_tournament_group_id) {
                $this->recalculateStandings($competition, $match->competition_tournament_group_id);
            }

            return $correction;
        });
    }

    public function recalculateStandings(Competition $competition, int $groupId): array
    {
        $group = CompetitionTournamentGroup::query()
            ->where('competition_id', $competition->id)
            ->whereKey($groupId)
            ->with('entries')
            ->firstOrFail();

        $rows = $group->entries->mapWithKeys(fn ($entry) => [$entry->competition_roster_entry_id => [
            'competition_roster_entry_id' => $entry->competition_roster_entry_id,
            'display_name' => $entry->display_name,
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'points' => 0,
            'score_for' => 0, 'score_against' => 0, 'score_difference' => 0,
        ]])->all();

        CompetitionTournamentMatch::query()
            ->where('competition_tournament_group_id', $group->id)
            ->where('status', 'completed')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('match_number')
            ->get()
            ->each(function (CompetitionTournamentMatch $match) use (&$rows): void {
                foreach ([[$match->home_roster_entry_id, $match->home_score, $match->away_score], [$match->away_roster_entry_id, $match->away_score, $match->home_score]] as [$id, $for, $against]) {
                    if (! isset($rows[$id])) {
                        continue;
                    }
                    $rows[$id]['played']++;
                    $rows[$id]['score_for'] += $for;
                    $rows[$id]['score_against'] += $against;
                    $rows[$id]['score_difference'] = $rows[$id]['score_for'] - $rows[$id]['score_against'];
                    $rows[$id][$for > $against ? 'wins' : ($for === $against ? 'draws' : 'losses')]++;
                    $rows[$id]['points'] += $for > $against ? 3 : ($for === $against ? 1 : 0);
                }
            });

        $ranked = collect($rows)->sortBy([
            ['points', 'desc'], ['score_difference', 'desc'], ['score_for', 'desc'], ['display_name', 'asc'],
        ])->values();

        CompetitionTournamentStanding::query()->where('competition_tournament_group_id', $group->id)->delete();

        return $ranked->map(function (array $row, int $index) use ($competition, $group): CompetitionTournamentStanding {
            return CompetitionTournamentStanding::query()->create(array_merge($row, [
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'competition_tournament_group_id' => $group->id,
                'rank' => $index + 1,
                'metadata' => [],
            ]));
        })->all();
    }

    /** @param array<int, array<string, mixed>> $entries */
    public function createLineup(Competition $competition, User $actor, array $data, array $entries = []): CompetitionLineup
    {
        $this->authorize($competition->club, $actor);
        $this->assertDeadlineOpen($competition, 'lineup_deadline_at');
        $this->assertLinksBelongToCompetition($competition, $data, [
            'competition_registration_id' => CompetitionRegistration::class,
            'competition_class_id' => CompetitionClass::class,
            'event_id' => Event::class,
        ]);

        return DB::transaction(function () use ($competition, $data, $entries): CompetitionLineup {
            $lineup = CompetitionLineup::query()->create([
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'competition_registration_id' => $data['competition_registration_id'] ?? null,
                'competition_class_id' => $data['competition_class_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'sport_type' => $data['sport_type'] ?? ($competition->metadata['sport_type'] ?? null),
                'name' => $data['name'],
                'status' => $data['status'] ?? 'draft',
                'metadata' => $data['metadata'] ?? [],
            ]);

            foreach ($entries as $index => $entry) {
                $this->assertRosterEntryBelongsToCompetition($competition, $entry['competition_roster_entry_id'] ?? null);
                $lineup->entries()->create([
                    'club_id' => $competition->club_id,
                    'competition_id' => $competition->id,
                    'competition_roster_entry_id' => $entry['competition_roster_entry_id'] ?? null,
                    'user_id' => $entry['user_id'] ?? null,
                    'display_name' => $entry['display_name'] ?? null,
                    'role' => $entry['role'] ?? 'starter',
                    'position' => $entry['position'] ?? null,
                    'sort_order' => $entry['sort_order'] ?? $index,
                    'metadata' => $entry['metadata'] ?? [],
                ]);
            }

            return $lineup->fresh('entries');
        });
    }

    /** @param array<int, array<string, mixed>> $entries */
    public function createStartList(Competition $competition, User $actor, array $entries): array
    {
        $this->authorize($competition->club, $actor);
        $this->assertDeadlineOpen($competition, 'start_list_deadline_at');

        return DB::transaction(function () use ($competition, $entries): array {
            return array_map(function (array $entry) use ($competition): CompetitionStartListEntry {
                $this->assertLinksBelongToCompetition($competition, $entry, [
                    'competition_class_id' => CompetitionClass::class,
                    'event_id' => Event::class,
                ]);
                $this->assertRosterEntryBelongsToCompetition($competition, $entry['competition_roster_entry_id'] ?? null);

                return CompetitionStartListEntry::query()->create([
                    'club_id' => $competition->club_id,
                    'competition_id' => $competition->id,
                    'competition_class_id' => $entry['competition_class_id'] ?? null,
                    'competition_roster_entry_id' => $entry['competition_roster_entry_id'] ?? null,
                    'event_id' => $entry['event_id'] ?? null,
                    'heat' => $entry['heat'] ?? null,
                    'lane' => $entry['lane'] ?? null,
                    'start_number' => $entry['start_number'] ?? null,
                    'scheduled_start_at' => $entry['scheduled_start_at'] ?? null,
                    'status' => $entry['status'] ?? 'planned',
                    'metadata' => $entry['metadata'] ?? [],
                ]);
            }, $entries);
        });
    }

    /** @param array<int, array<string, mixed>> $legs */
    public function createRelayTeam(Competition $competition, User $actor, array $data, array $legs): CompetitionRelayTeam
    {
        $this->authorize($competition->club, $actor);
        $this->assertDeadlineOpen($competition, 'relay_deadline_at');
        $this->assertLinksBelongToCompetition($competition, $data, [
            'competition_class_id' => CompetitionClass::class,
            'event_id' => Event::class,
        ]);

        return DB::transaction(function () use ($competition, $data, $legs): CompetitionRelayTeam {
            $relay = CompetitionRelayTeam::query()->create([
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'competition_class_id' => $data['competition_class_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'name' => $data['name'],
                'discipline' => $data['discipline'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'metadata' => $data['metadata'] ?? [],
            ]);

            foreach ($legs as $index => $leg) {
                $this->assertRosterEntryBelongsToCompetition($competition, $leg['competition_roster_entry_id'] ?? null);
                $relay->legs()->create([
                    'club_id' => $competition->club_id,
                    'competition_id' => $competition->id,
                    'competition_roster_entry_id' => $leg['competition_roster_entry_id'] ?? null,
                    'user_id' => $leg['user_id'] ?? null,
                    'leg_number' => $leg['leg_number'] ?? ($index + 1),
                    'segment' => $leg['segment'] ?? null,
                    'metadata' => $leg['metadata'] ?? [],
                ]);
            }

            return $relay->fresh('legs');
        });
    }

    public function recordSubstitution(Competition $competition, User $actor, array $data): CompetitionSubstitution
    {
        $this->authorize($competition->club, $actor);
        $this->assertLinksBelongToCompetition($competition, $data, ['event_id' => Event::class]);
        $this->assertRosterEntryBelongsToCompetition($competition, $data['out_roster_entry_id'] ?? null, 'out_roster_entry_id');
        $this->assertRosterEntryBelongsToCompetition($competition, $data['in_roster_entry_id'] ?? null, 'in_roster_entry_id');

        return CompetitionSubstitution::query()->create([
            'club_id' => $competition->club_id,
            'competition_id' => $competition->id,
            'event_id' => $data['event_id'] ?? null,
            'out_roster_entry_id' => $data['out_roster_entry_id'] ?? null,
            'in_roster_entry_id' => $data['in_roster_entry_id'] ?? null,
            'minute' => $data['minute'] ?? null,
            'period' => $data['period'] ?? null,
            'reason' => $data['reason'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function recordPlayingTime(Competition $competition, User $actor, array $data): CompetitionPlayingTimeEntry
    {
        $this->authorize($competition->club, $actor);
        $this->assertLinksBelongToCompetition($competition, $data, ['event_id' => Event::class]);
        $this->assertRosterEntryBelongsToCompetition($competition, $data['competition_roster_entry_id'] ?? null);

        return CompetitionPlayingTimeEntry::query()->updateOrCreate([
            'competition_id' => $competition->id,
            'event_id' => $data['event_id'] ?? null,
            'competition_roster_entry_id' => $data['competition_roster_entry_id'] ?? null,
        ], [
            'club_id' => $competition->club_id,
            'minutes_played' => $data['minutes_played'] ?? 0,
            'started_period' => $data['started_period'] ?? null,
            'ended_period' => $data['ended_period'] ?? null,
            'segments' => $data['segments'] ?? [],
        ]);
    }

    public function assignOfficial(Competition $competition, User $actor, array $data): CompetitionOfficialAssignment
    {
        $this->authorize($competition->club, $actor);
        $this->assertLinksBelongToCompetition($competition, $data, [
            'event_id' => Event::class,
            'competition_venue_id' => CompetitionVenue::class,
        ]);

        return CompetitionOfficialAssignment::query()->create([
            'club_id' => $competition->club_id,
            'competition_id' => $competition->id,
            'event_id' => $data['event_id'] ?? null,
            'competition_venue_id' => $data['competition_venue_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'display_name' => $data['display_name'] ?? null,
            'assignment_type' => $data['assignment_type'] ?? 'referee',
            'role' => $data['role'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    private function createRoundRobinMatches(Competition $competition, CompetitionTournamentGroup $group, mixed $classId, int $startMatchNumber): int
    {
        $entries = $group->entries->sortBy([['seed', 'asc'], ['display_name', 'asc']])->values();
        if ($entries->count() < 2) {
            return $startMatchNumber;
        }

        $slots = $entries->all();
        if (count($slots) % 2 === 1) {
            $slots[] = null;
        }

        $rounds = count($slots) - 1;
        $half = (int) (count($slots) / 2);
        $matchNumber = $startMatchNumber;

        for ($round = 1; $round <= $rounds; $round++) {
            for ($i = 0; $i < $half; $i++) {
                $home = $slots[$i];
                $away = $slots[count($slots) - 1 - $i];
                if (! $home || ! $away) {
                    continue;
                }

                CompetitionTournamentMatch::query()->create([
                    'club_id' => $competition->club_id,
                    'competition_id' => $competition->id,
                    'competition_class_id' => $classId,
                    'competition_tournament_group_id' => $group->id,
                    'home_roster_entry_id' => $home->competition_roster_entry_id,
                    'away_roster_entry_id' => $away->competition_roster_entry_id,
                    'phase' => 'group',
                    'round_number' => $round,
                    'match_number' => $matchNumber++,
                    'home_label' => $home->display_name,
                    'away_label' => $away->display_name,
                    'status' => 'planned',
                    'metadata' => ['group' => $group->name],
                ]);
            }

            $fixed = array_shift($slots);
            $last = array_pop($slots);
            array_unshift($slots, $fixed, $last);
        }

        return $matchNumber;
    }

    private function generateKnockoutRound(Competition $competition, User $actor, array $options): void
    {
        $qualifiersPerGroup = max(1, (int) ($options['qualifiers_per_group'] ?? 2));
        $classId = $options['competition_class_id'] ?? null;
        $groups = CompetitionTournamentGroup::query()
            ->where('competition_id', $competition->id)
            ->with('entries')
            ->orderBy('sort_order')
            ->get();

        $slot = 1;
        $matchNumber = CompetitionTournamentMatch::query()->where('competition_id', $competition->id)->max('match_number') + 1;
        $labels = [];

        foreach ($groups as $group) {
            for ($rank = 1; $rank <= $qualifiersPerGroup; $rank++) {
                $labels[] = $rank.'. '.$group->name;
            }
        }

        for ($i = 0; $i < count($labels); $i += 2) {
            CompetitionTournamentMatch::query()->create([
                'club_id' => $competition->club_id,
                'competition_id' => $competition->id,
                'competition_class_id' => $classId,
                'phase' => 'knockout',
                'round_number' => 1,
                'match_number' => $matchNumber++,
                'home_label' => $labels[$i],
                'away_label' => $labels[$i + 1] ?? 'Freilos',
                'status' => isset($labels[$i + 1]) ? 'planned' : 'bye',
                'metadata' => ['slot' => $slot++, 'generated_by' => $actor->id],
            ]);
        }
    }

    private function detectMatchConflicts(CompetitionTournamentMatch $match, array $after): array
    {
        $conflicts = [];

        if (($after['status'] ?? $match->status) === 'completed' && (! is_numeric($after['home_score'] ?? null) || ! is_numeric($after['away_score'] ?? null))) {
            $conflicts[] = 'Abgeschlossene Spiele benötigen Heim- und Auswärtspunktzahl.';
        }

        if (! empty($after['scheduled_at'])) {
            $overlap = CompetitionTournamentMatch::query()
                ->where('competition_id', $match->competition_id)
                ->whereKeyNot($match->id)
                ->where('scheduled_at', $after['scheduled_at'])
                ->where(function ($query) use ($match): void {
                    $query->whereIn('home_roster_entry_id', array_filter([$match->home_roster_entry_id, $match->away_roster_entry_id]))
                        ->orWhereIn('away_roster_entry_id', array_filter([$match->home_roster_entry_id, $match->away_roster_entry_id]));
                })
                ->exists();

            if ($overlap) {
                $conflicts[] = 'Ein beteiligter Starter ist zum selben Zeitpunkt bereits angesetzt.';
            }
        }

        return $conflicts;
    }

    private function authorize(Club $club, User $actor): void
    {
        if (! ClubPermissions::allows($club, $actor, ClubPermissions::EVENTS_EDIT)) {
            throw new AuthorizationException;
        }
    }

    private function assertDeadlineOpen(Competition $competition, string $metadataKey): void
    {
        $deadline = $competition->metadata[$metadataKey] ?? $competition->registration_deadline_at;

        if ($deadline && Carbon::parse($deadline)->isPast()) {
            throw ValidationException::withMessages([$metadataKey => 'Die Planungsfrist ist abgelaufen.']);
        }
    }

    /** @param array<string, class-string<Model>> $links */
    private function assertLinksBelongToCompetition(Competition $competition, array $data, array $links): void
    {
        foreach ($links as $field => $modelClass) {
            if (empty($data[$field])) {
                continue;
            }

            $query = $modelClass::query()->whereKey($data[$field])->where('club_id', $competition->club_id);
            if ($field !== 'event_id') {
                $query->where('competition_id', $competition->id);
            } else {
                $query->where('competition_id', $competition->id);
            }

            if (! $query->exists()) {
                throw ValidationException::withMessages([$field => __('validation.exists', ['attribute' => $field])]);
            }
        }
    }

    private function assertRosterEntryBelongsToCompetition(Competition $competition, mixed $rosterEntryId, string $field = 'competition_roster_entry_id'): void
    {
        if (! $rosterEntryId) {
            return;
        }

        $belongs = CompetitionRosterEntry::query()
            ->whereKey($rosterEntryId)
            ->where('competition_id', $competition->id)
            ->where('club_id', $competition->club_id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([$field => __('validation.exists', ['attribute' => $field])]);
        }
    }
}
