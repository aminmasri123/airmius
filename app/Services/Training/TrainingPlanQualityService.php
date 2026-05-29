<?php

namespace App\Services\Training;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrainingPlanQualityService
{
    public function evaluate(array $plan, array $profileReadiness, array $context): array
    {
        $items = collect($plan['items'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $warnings = [];
        $suggestions = [];
        $checks = [];
        $score = 100;
        $level = (string) ($context['level'] ?? $profileReadiness['profile']['experience_level'] ?? 'intermediate');
        $sportGroup = (string) ($profileReadiness['group'] ?? 'generic');

        if ($items->isEmpty()) {
            return [
                'score' => 0,
                'risk' => 'hoch',
                'warnings' => ['Der Plan enthält keine Trainingseinheiten.'],
                'suggestions' => ['Erstelle den Plan neu oder ergänze Einheiten manuell.'],
                'checks' => [[
                    'key' => 'items',
                    'label' => 'Einheiten',
                    'status' => 'danger',
                    'message' => 'Keine Einheiten gefunden.',
                ]],
            ];
        }

        [$progressionWarnings, $progressionSuggestions, $progressionCheck, $progressionPenalty] = $this->checkProgression($items, $level, $sportGroup);
        $warnings = [...$warnings, ...$progressionWarnings];
        $suggestions = [...$suggestions, ...$progressionSuggestions];
        $checks[] = $progressionCheck;
        $score -= $progressionPenalty;

        [$loadWarnings, $loadSuggestions, $loadCheck, $loadPenalty] = $this->checkLoadBalance($items);
        $warnings = [...$warnings, ...$loadWarnings];
        $suggestions = [...$suggestions, ...$loadSuggestions];
        $checks[] = $loadCheck;
        $score -= $loadPenalty;

        [$specificWarnings, $specificSuggestions, $specificChecks, $specificPenalty] = $this->checkSportSpecifics($items, $sportGroup, $profileReadiness);
        $warnings = [...$warnings, ...$specificWarnings];
        $suggestions = [...$suggestions, ...$specificSuggestions];
        $checks = [...$checks, ...$specificChecks];
        $score -= $specificPenalty;

        if (! ($profileReadiness['ready'] ?? false)) {
            $unknownLabels = collect($profileReadiness['unknown'] ?? [])->pluck('label')->filter()->implode(', ');
            $warnings[] = $unknownLabels !== ''
                ? 'Einige Pflichtdaten wurden als unbekannt markiert: '.$unknownLabels.'. Der Plan kann nur vorsichtig bewertet werden.'
                : 'Das Sportprofil ist nicht vollständig. Der Plan kann nur grob bewertet werden.';
            $suggestions[] = 'Trage die fehlenden Leistungsdaten nach und generiere den Plan danach erneut.';
            $score -= 20;
            $checks[] = [
                'key' => 'profile',
                'label' => 'Athletenprofil',
                'status' => 'warning',
                'message' => 'Profil unvollständig.',
            ];
        } else {
            $checks[] = [
                'key' => 'profile',
                'label' => 'Athletenprofil',
                'status' => 'ok',
                'message' => 'Pflichtdaten vorhanden.',
            ];
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'risk' => $score >= 85 ? 'niedrig' : ($score >= 65 ? 'mittel' : 'hoch'),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'suggestions' => array_values(array_unique(array_filter($suggestions))),
            'checks' => $checks,
        ];
    }

    private function checkProgression(Collection $items, string $level, string $sportGroup): array
    {
        $warnings = [];
        $suggestions = [];
        $penalty = 0;
        $limit = match ($level) {
            'beginner' => 15,
            'advanced', 'elite' => 25,
            default => 20,
        };

        $weeklyLoads = $items
            ->groupBy(fn (array $item) => max(1, (int) ($item['week'] ?? 1)))
            ->map(function (Collection $weekItems) use ($sportGroup) {
                $distance = $weekItems->sum(fn (array $item) => (float) ($item['distance_km'] ?? 0));
                $minutes = $weekItems->sum(fn (array $item) => (int) ($item['duration_minutes'] ?? 0));

                return $sportGroup === 'running' || $sportGroup === 'cycling'
                    ? max($distance, $minutes / 10)
                    : $minutes;
            })
            ->sortKeys();

        $previous = null;
        foreach ($weeklyLoads as $week => $load) {
            if ($previous !== null && $previous > 0 && $load > 0) {
                $increase = (($load - $previous) / $previous) * 100;

                if ($increase > $limit) {
                    $warnings[] = "Woche {$week} steigert die Belastung um ca. ".round($increase)."% und liegt über der empfohlenen Grenze von {$limit}%.";
                    $suggestions[] = "Reduziere Woche {$week} leicht oder verschiebe eine harte Einheit in eine ruhigere Woche.";
                    $penalty += min(20, (int) round(($increase - $limit) / 2));
                }
            }

            if ($load > 0) {
                $previous = $load;
            }
        }

        return [
            $warnings,
            $suggestions,
            [
                'key' => 'progression',
                'label' => 'Progression',
                'status' => $warnings ? 'warning' : 'ok',
                'message' => $warnings ? 'Mindestens eine Woche steigert zu stark.' : 'Wochensteigerung wirkt kontrolliert.',
            ],
            $penalty,
        ];
    }

    private function checkLoadBalance(Collection $items): array
    {
        $warnings = [];
        $suggestions = [];
        $penalty = 0;
        $hardItems = $items->filter(fn (array $item) => in_array($item['intensity'] ?? '', ['hart'], true) || in_array($item['load'] ?? '', ['high', 'test'], true));
        $hardByWeek = $hardItems->groupBy(fn (array $item) => max(1, (int) ($item['week'] ?? 1)));

        foreach ($hardByWeek as $week => $weekHardItems) {
            if ($weekHardItems->count() > 2) {
                $warnings[] = "Woche {$week} enthält mehr als zwei harte Einheiten.";
                $suggestions[] = "Plane in Woche {$week} maximal zwei harte Reize und fülle den Rest locker oder technisch.";
                $penalty += 12;
            }
        }

        $recoveryCount = $items->filter(function (array $item) {
            $text = Str::lower(($item['training_type'] ?? '').' '.($item['intensity'] ?? '').' '.($item['title'] ?? ''));

            return str_contains($text, 'recovery') || str_contains($text, 'regeneration') || str_contains($text, 'locker');
        })->count();

        if ($items->count() >= 6 && $recoveryCount === 0) {
            $warnings[] = 'Der Plan enthält keine klar erkennbare Regenerationseinheit.';
            $suggestions[] = 'Ergänze mindestens eine lockere oder regenerative Einheit pro Trainingswoche.';
            $penalty += 15;
        }

        return [
            $warnings,
            $suggestions,
            [
                'key' => 'load_balance',
                'label' => 'Belastung',
                'status' => $warnings ? 'warning' : 'ok',
                'message' => $warnings ? 'Belastung braucht Feinschliff.' : 'Harte und lockere Reize wirken plausibel verteilt.',
            ],
            $penalty,
        ];
    }

    private function checkSportSpecifics(Collection $items, string $sportGroup, array $profileReadiness): array
    {
        return match ($sportGroup) {
            'running' => $this->checkRunning($items, $profileReadiness),
            'strength' => $this->checkStrength($items),
            'swimming' => $this->checkSwimming($items),
            'team' => $this->checkTeamSport($items),
            default => [[], [], [[
                'key' => 'sport_specific',
                'label' => 'Sportart-Logik',
                'status' => 'ok',
                'message' => 'Allgemeine Sicherheitsregeln angewendet.',
            ]], 0],
        };
    }

    private function checkRunning(Collection $items, array $profileReadiness): array
    {
        $warnings = [];
        $suggestions = [];
        $penalty = 0;
        $metrics = $profileReadiness['profile']['metrics'] ?? [];
        $hasPaceReference = filled($metrics['best_5k_time'] ?? null)
            || filled($metrics['best_10k_time'] ?? null)
            || filled($metrics['vma_kmh'] ?? null);

        foreach ($items as $item) {
            $metricsText = Str::lower(json_encode($item['metrics'] ?? [], JSON_UNESCAPED_UNICODE) ?: '');

            if (! $hasPaceReference && preg_match('/\d{1,2}(?::\d{2}|[,.]\d+)?\s*min\s*\/\s*km/i', $metricsText) === 1) {
                $warnings[] = 'Der Plan enthält eine exakte Lauf-Pace, obwohl keine 5-km-/10-km-Zeit oder VMA hinterlegt ist.';
                $suggestions[] = 'Nutze ohne Leistungsreferenz relative Angaben wie Zone 2, 5-km-Gefühl oder RPE.';
                $penalty += 12;
                break;
            }
        }

        $longRuns = $items->filter(fn (array $item) => in_array($item['training_type'] ?? '', ['long_run'], true));
        $maxLongRun = $longRuns->max(fn (array $item) => (float) ($item['distance_km'] ?? 0));
        $currentLongest = (float) ($metrics['longest_run_km'] ?? 0);

        if ($currentLongest > 0 && $maxLongRun > $currentLongest * 1.5) {
            $warnings[] = 'Der längste Lauf im Plan ist deutlich länger als der aktuell längste Lauf.';
            $suggestions[] = 'Steigere den Long Run schrittweise statt direkt stark zu springen.';
            $penalty += 14;
        }

        return [
            $warnings,
            $suggestions,
            [[
                'key' => 'running_logic',
                'label' => 'Lauf-Logik',
                'status' => $warnings ? 'warning' : 'ok',
                'message' => $warnings ? 'Laufdaten und Pace prüfen.' : 'Lauf-Pace und Long Runs wirken plausibel.',
            ]],
            $penalty,
        ];
    }

    private function checkStrength(Collection $items): array
    {
        $hardStrength = $items->filter(fn (array $item) => in_array($item['load'] ?? '', ['high', 'test'], true) || ($item['intensity'] ?? '') === 'hart');
        $warnings = [];
        $suggestions = [];
        $penalty = 0;

        if ($hardStrength->count() > max(2, ceil($items->count() / 2))) {
            $warnings[] = 'Der Kraftplan enthält sehr viele harte Einheiten.';
            $suggestions[] = 'Verteile schwere Tage mit leichter Technik, Mobility oder Volumentagen.';
            $penalty = 12;
        }

        return [$warnings, $suggestions, [[
            'key' => 'strength_logic',
            'label' => 'Kraft-Logik',
            'status' => $warnings ? 'warning' : 'ok',
            'message' => $warnings ? 'Regeneration zwischen schweren Reizen prüfen.' : 'Kraftbelastung wirkt grundsätzlich plausibel.',
        ]], $penalty];
    }

    private function checkSwimming(Collection $items): array
    {
        $hasTechnique = $items->contains(fn (array $item) => str_contains(Str::lower(($item['training_type'] ?? '').' '.($item['title'] ?? '')), 'technik'));

        return [
            $hasTechnique ? [] : ['Der Schwimmplan enthält keine klar erkennbare Technikeinheit.'],
            $hasTechnique ? [] : ['Baue regelmäßig Technikserien für Wasserlage, Atmung oder Armzug ein.'],
            [[
                'key' => 'swim_logic',
                'label' => 'Schwimm-Logik',
                'status' => $hasTechnique ? 'ok' : 'warning',
                'message' => $hasTechnique ? 'Technikanteil vorhanden.' : 'Technikanteil fehlt.',
            ]],
            $hasTechnique ? 0 : 10,
        ];
    }

    private function checkTeamSport(Collection $items): array
    {
        $text = Str::lower($items->map(fn (array $item) => ($item['training_type'] ?? '').' '.($item['focus'] ?? '').' '.($item['title'] ?? ''))->implode(' '));
        $hasSkill = str_contains($text, 'technik') || str_contains($text, 'spiel') || str_contains($text, 'taktik');

        return [
            $hasSkill ? [] : ['Der Teamsport-Plan wirkt sehr konditionslastig und enthält wenig Technik/Spielnähe.'],
            $hasSkill ? [] : ['Ergänze technische, taktische oder spielnahe Einheiten passend zur Position.'],
            [[
                'key' => 'team_logic',
                'label' => 'Teamsport-Logik',
                'status' => $hasSkill ? 'ok' : 'warning',
                'message' => $hasSkill ? 'Technik oder Spielnähe vorhanden.' : 'Technik/Spielnähe fehlt.',
            ]],
            $hasSkill ? 0 : 10,
        ];
    }
}
