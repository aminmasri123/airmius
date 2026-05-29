<?php

namespace App\Services\Training;

use App\Models\Sport;
use App\Models\User;
use App\Models\UserSport;
use Illuminate\Support\Str;

class AthleteSportProfileService
{
    public function settingsPayload(User $user): array
    {
        $profiles = $user->sportProfiles()
            ->with('sport:id,name,slug,category')
            ->get()
            ->keyBy('sport_id');

        return Sport::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category'])
            ->map(function (Sport $sport) use ($profiles) {
                $profile = $profiles->get($sport->id);
                $fields = $this->fieldsForSport($sport->slug ?: $sport->name);
                $readiness = $this->readinessFromMetrics($profile?->performance_metrics ?? [], $fields, (bool) $profile);

                return [
                    'has_profile' => (bool) $profile,
                    'sport' => [
                        'id' => $sport->id,
                        'name' => $sport->name,
                        'slug' => $sport->slug,
                        'category' => $sport->category,
                    ],
                    'group' => $this->groupForSport($sport->slug ?: $sport->name),
                    'status' => $profile?->status ?? 'active',
                    'experience_level' => $profile?->experience_level ?? 'beginner',
                    'visibility' => $profile?->visibility ?? 'private',
                    'metrics' => $profile?->performance_metrics ?? [],
                    'metric_visibility' => $profile?->performance_visibility ?? [],
                    'fields' => $fields,
                    'readiness' => $readiness,
                    'completed_at' => $profile?->training_profile_completed_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    public function updateProfile(User $user, Sport $sport, array $data): UserSport
    {
        $fields = $this->fieldsForSport($sport->slug ?: $sport->name);
        $allowedKeys = collect($fields)->pluck('key')->all();
        $unknownKeys = collect($data['unknown_metrics'] ?? [])
            ->filter(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->intersect($allowedKeys)
            ->values()
            ->all();
        $metrics = $this->sanitizeMetrics($data['metrics'] ?? [], $fields);

        foreach ($unknownKeys as $key) {
            unset($metrics[$key]);
        }

        if ($unknownKeys !== []) {
            $metrics['_unknown_fields'] = $unknownKeys;
        }

        $visibility = collect($data['metric_visibility'] ?? [])
            ->only($allowedKeys)
            ->map(fn ($value) => in_array($value, ['private', 'trainer', 'public'], true) ? $value : 'private')
            ->all();
        $readiness = $this->readinessFromMetrics($metrics, $fields, true);

        foreach ($fields as $field) {
            $visibility[$field['key']] ??= $field['default_visibility'] ?? 'private';
        }

        return UserSport::updateOrCreate(
            [
                'user_id' => $user->id,
                'sport_id' => $sport->id,
            ],
            [
                'status' => $data['status'] ?? 'active',
                'experience_level' => $data['experience_level'] ?? 'beginner',
                'visibility' => $data['visibility'] ?? 'private',
                'performance_metrics' => $metrics,
                'performance_visibility' => $visibility,
                'training_profile_completed_at' => $readiness['ready'] ? now() : null,
            ],
        );
    }

    public function readiness(User $user, string $sportType): array
    {
        $sport = $this->resolveSport($sportType);
        $fields = $this->fieldsForSport($sport?->slug ?: $sportType);
        $profile = $sport
            ? $user->sportProfiles()->where('sport_id', $sport->id)->with('sport:id,name,slug,category')->first()
            : null;

        $readiness = $this->readinessFromMetrics($profile?->performance_metrics ?? [], $fields, (bool) $profile);

        return [
            ...$readiness,
            'sport' => $sport ? [
                'id' => $sport->id,
                'name' => $sport->name,
                'slug' => $sport->slug,
                'category' => $sport->category,
            ] : [
                'id' => null,
                'name' => $sportType,
                'slug' => $sportType,
                'category' => null,
            ],
            'group' => $this->groupForSport($sport?->slug ?: $sportType),
            'profile' => $profile ? [
                'status' => $profile->status,
                'experience_level' => $profile->experience_level,
                'metrics' => $this->privateMetricsForAi($profile),
            ] : null,
            'fields' => $fields,
        ];
    }

    public function fieldsForSport(string $sportType): array
    {
        return match ($this->groupForSport($sportType)) {
            'running' => [
                $this->field('experience', 'Erfahrung', 'text', null, true, 'Seit wann läufst du regelmäßig?'),
                $this->field('weekly_km', 'Aktuelle Wochen-km', 'number', 'km', true, 'Wie viele Kilometer läufst du aktuell pro Woche?'),
                $this->field('longest_run_km', 'Längster Lauf aktuell', 'number', 'km', true, 'Der längste lockere Lauf der letzten 4 Wochen.'),
                $this->field('run_best_100m_time', '100-m-Bestzeit', 'text', null, false, 'Optional: Sprintreferenz, zum Beispiel 14.2 s.'),
                $this->field('run_best_200m_time', '200-m-Bestzeit', 'text', null, false, 'Optional: Sprint- und Schnelligkeitsreferenz.'),
                $this->field('run_best_400m_time', '400-m-Bestzeit', 'text', null, false, 'Optional: Tempohärte und Intervallorientierung.'),
                $this->field('run_best_800m_time', '800-m-Bestzeit', 'text', null, false, 'Optional: Kurzmittelstrecke.'),
                $this->field('run_best_1500m_time', '1500-m-Bestzeit', 'text', null, false, 'Optional: Mittelstreckenreferenz.'),
                $this->field('run_best_3000m_time', '3000-m-Bestzeit', 'text', null, false, 'Optional: VO2max-nahe Referenz.'),
                $this->field('best_5k_time', '5-km-Bestzeit', 'text', null, false, 'Optional: hilft bei Zielpace und Tempodauer.'),
                $this->field('best_10k_time', '10-km-Bestzeit', 'text', null, false, 'Optional: hilft bei Schwellentempo und Ausdauer.'),
                $this->field('best_half_marathon_time', 'Halbmarathon-Bestzeit', 'text', null, false, 'Optional: 21,1-km-Referenz.'),
                $this->field('best_marathon_time', 'Marathon-Bestzeit', 'text', null, false, 'Optional: 42,2-km-Referenz.'),
                $this->field('vma_kmh', 'VMA', 'number', 'km/h', false, 'Falls bekannt: maximale aerobe Geschwindigkeit.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Knie, Achillessehne, Rücken, Pause nach Krankheit usw.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Mo, Mi, Sa.'),
            ],
            'strength' => [
                $this->field('training_experience_months', 'Trainingserfahrung', 'number', 'Monate', true, 'Wie lange trainierst du regelmäßig Kraft?'),
                $this->field('weekly_sessions', 'Einheiten pro Woche', 'number', null, true, 'Wie oft trainierst du aktuell pro Woche?'),
                $this->field('training_goal', 'Trainingsziel', 'text', null, true, 'Zum Beispiel Muskelaufbau, Maximalkraft, Fettabbau, allgemeine Fitness.'),
                $this->field('equipment', 'Equipment', 'textarea', null, true, 'Gym, Kurzhanteln, Langhantel, Maschinen, Zuhause.'),
                $this->field('bodyweight_kg', 'Körpergewicht', 'number', 'kg', false, 'Optional, hilft bei relativer Kraft und Belastung.'),
                $this->field('bench_press_1rm_kg', 'Bankdrücken 1RM', 'number', 'kg', false, 'Geschätztes oder getestetes 1RM.'),
                $this->field('squat_1rm_kg', 'Kniebeuge 1RM', 'number', 'kg', false, 'Geschätztes oder getestetes 1RM.'),
                $this->field('deadlift_1rm_kg', 'Kreuzheben 1RM', 'number', 'kg', false, 'Geschätztes oder getestetes 1RM.'),
                $this->field('overhead_press_1rm_kg', 'Schulterdrücken 1RM', 'number', 'kg', false, 'Geschätztes oder getestetes 1RM.'),
                $this->field('leg_press_1rm_kg', 'Beinpresse max.', 'number', 'kg', false, 'Maximale saubere Wiederholung oder geschätztes Maximum.'),
                $this->field('pullups_max_reps', 'Klimmzüge max.', 'number', 'Wdh.', false, 'Maximale saubere Wiederholungen.'),
                $this->field('dips_max_reps', 'Dips max.', 'number', 'Wdh.', false, 'Maximale saubere Wiederholungen.'),
                $this->field('pushups_max_reps', 'Liegestütze max.', 'number', 'Wdh.', false, 'Maximale saubere Wiederholungen.'),
                $this->field('plank_seconds', 'Plank-Zeit', 'number', 'Sek.', false, 'Maximale saubere Haltezeit.'),
                $this->field('wall_sit_seconds', 'Wall-Sit-Zeit', 'number', 'Sek.', false, 'Maximale saubere Haltezeit.'),
                $this->field('burpees_1min', 'Burpees in 1 Minute', 'number', 'Wdh.', false, 'Saubere Wiederholungen in 60 Sekunden.'),
                $this->field('jump_rope_1min', 'Seilspringen max./Minute', 'number', 'Sprünge', false, 'Maximale saubere Sprünge in 60 Sekunden.'),
                $this->field('main_lifts', 'Weitere Kraftwerte / Notizen', 'textarea', null, false, 'Zum Beispiel Rudern 70x8, Hip Thrust 100x10, Maschineinstellungen.'),
                $this->field('weak_points', 'Schwachstellen', 'textarea', null, false, 'Zum Beispiel Schulter, Core, Mobilität, Griffkraft.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Schmerzen, Übungen vermeiden, Bewegungseinschränkungen.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Mo, Di, Do, Sa.'),
            ],
            'cycling' => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann fährst du regelmäßig Rad?'),
                $this->field('weekly_km', 'Aktuelle Wochen-km', 'number', 'km', true, 'Radumfang pro Woche.'),
                $this->field('longest_ride_km', 'Längste Fahrt aktuell', 'number', 'km', true, 'Längste Fahrt der letzten 4 Wochen.'),
                $this->field('weekly_elevation_m', 'Höhenmeter pro Woche', 'number', 'm', false, 'Optional, wichtig für Bergtraining und Belastung.'),
                $this->field('ftp_watts', 'FTP', 'number', 'Watt', false, 'Falls bekannt.'),
                $this->field('power_20min_watts', '20-Minuten-Leistung', 'number', 'Watt', false, 'Optional, falls FTP nicht bekannt ist.'),
                $this->field('threshold_hr_bpm', 'Schwellenpuls', 'number', 'bpm', false, 'Optional, falls du nach Herzfrequenz trainierst.'),
                $this->field('max_hr_bpm', 'Maximalpuls', 'number', 'bpm', false, 'Optional für Trainingszonen.'),
                $this->field('avg_speed_kmh', 'Durchschnittsgeschwindigkeit', 'number', 'km/h', false, 'Typischer Schnitt bei ruhiger Fahrt.'),
                $this->field('cadence_rpm', 'Trittfrequenz', 'number', 'rpm', false, 'Typische Kadenz bei lockerer Fahrt.'),
                $this->field('bike_type', 'Radtyp', 'text', null, true, 'Rennrad, MTB, Gravel, Citybike.'),
                $this->field('terrain_preference', 'Terrain / Strecke', 'text', null, false, 'Straße, Gravel, Trail, Berge, flach, Rolle.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Knie, Rücken, Nacken, Sitzprobleme.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Di, Do, So.'),
            ],
            'swimming' => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann schwimmst du regelmäßig?'),
                $this->field('pool_length_m', 'Beckenlänge', 'number', 'm', true, 'Zum Beispiel 25 oder 50.'),
                $this->field('technique_level', 'Technikniveau', 'text', null, true, 'Anfänger, solide, fortgeschritten; Kraul/Rücken/Brust.'),
                $this->field('main_stroke', 'Hauptlage', 'text', null, true, 'Kraul, Brust, Rücken, Schmetterling oder gemischt.'),
                $this->field('swim_best_50m_time', '50-m-Zeit', 'text', null, false, 'Optional für Sprinttempo.'),
                $this->field('swim_best_100m_time', '100-m-Zeit', 'text', null, false, 'Optional für Intervalltempo.'),
                $this->field('swim_best_200m_time', '200-m-Zeit', 'text', null, false, 'Optional für Technik und Tempoausdauer.'),
                $this->field('swim_best_400m_time', '400-m-Zeit', 'text', null, false, 'Optional für Ausdauerpace.'),
                $this->field('swim_best_800m_time', '800-m-Zeit', 'text', null, false, 'Optional für längere Ausdauer.'),
                $this->field('swim_best_1500m_time', '1500-m-Zeit', 'text', null, false, 'Optional für Langdistanz.'),
                $this->field('weekly_meters', 'Aktuelle Wochenmeter', 'number', 'm', true, 'Wie viele Meter schwimmst du aktuell pro Woche?'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Schulter, Rücken, Atmung, Technikprobleme.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Mo, Fr.'),
            ],
            'team' => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann spielst du diese Sportart regelmäßig?'),
                $this->field('position', 'Position / Rolle', 'text', null, true, 'Zum Beispiel Stürmer, Torwart, Spielmacher.'),
                $this->field('season_phase', 'Saisonphase', 'text', null, true, 'Vorbereitung, Saison, Pause, Reha.'),
                $this->field('match_day', 'Spieltag', 'text', null, false, 'Zum Beispiel Sonntag.'),
                $this->field('training_days', 'Teamtrainingstage', 'text', null, true, 'Zum Beispiel Di und Do.'),
                $this->field('matches_per_week', 'Spiele pro Woche', 'number', null, false, 'Optional, wichtig für Belastungssteuerung.'),
                $this->field('match_minutes', 'Spielminuten', 'number', 'min', false, 'Typische Einsatzzeit pro Spiel.'),
                $this->field('preferred_foot_or_side', 'Starke Seite', 'text', null, false, 'Zum Beispiel rechts, links, beidfüßig, Wurfarm.'),
                $this->field('sprint_30m_time', '30-m-Sprint', 'text', null, false, 'Optional, zum Beispiel 4.3 s.'),
                $this->field('cooper_12min_m', 'Cooper-Test', 'number', 'm', false, 'Meter in 12 Minuten, falls bekannt.'),
                $this->field('yo_yo_level', 'Yo-Yo-Test', 'text', null, false, 'Optional, falls dein Team diesen Test nutzt.'),
                $this->field('vertical_jump_cm', 'Sprunghöhe', 'number', 'cm', false, 'Optional für Explosivität.'),
                $this->field('focus_needs', 'Schwerpunkte', 'textarea', null, true, 'Technik, Sprint, Ausdauer, Beweglichkeit, Zweikampf.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Belastung, Schmerzen, Dinge vermeiden.'),
            ],
            'racket' => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann spielst du diese Sportart regelmäßig?'),
                $this->field('weekly_sessions', 'Einheiten pro Woche', 'number', null, true, 'Wie oft trainierst oder spielst du pro Woche?'),
                $this->field('playing_level', 'Spielniveau', 'text', null, true, 'Zum Beispiel Hobby, Verein, Liga, LK, Ranking oder Turniererfahrung.'),
                $this->field('dominant_hand', 'Starke Hand', 'text', null, true, 'Rechts, links oder beidseitig.'),
                $this->field('match_frequency', 'Matchhäufigkeit', 'text', null, false, 'Wie oft spielst du Matches oder Turniere?'),
                $this->field('training_goal', 'Trainingsziel', 'text', null, true, 'Technik, Fitness, Aufschlag, Beinarbeit, Matchform oder Ranking.'),
                $this->field('serve_speed_kmh', 'Aufschlaggeschwindigkeit', 'number', 'km/h', false, 'Optional, falls bekannt.'),
                $this->field('focus_needs', 'Schwerpunkte', 'textarea', null, true, 'Technik, Beinarbeit, Ausdauer, Reaktion, Taktik.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Schulter, Ellbogen, Knie, Rücken usw.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Mo, Mi, Sa.'),
            ],
            'combat' => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann trainierst du diese Kampfsportart regelmäßig?'),
                $this->field('weekly_sessions', 'Einheiten pro Woche', 'number', null, true, 'Wie oft trainierst du pro Woche?'),
                $this->field('training_goal', 'Trainingsziel', 'text', null, true, 'Technik, Fitness, Wettkampf, Gürtelprüfung, Selbstverteidigung.'),
                $this->field('technical_focus', 'Technischer Schwerpunkt', 'textarea', null, true, 'Stand, Boden, Schläge, Tritte, Würfe, Griffkampf oder Defense.'),
                $this->field('weight_class_kg', 'Gewichtsklasse / Körpergewicht', 'number', 'kg', false, 'Optional, relevant für Wettkampf und Belastung.'),
                $this->field('sparring_frequency', 'Sparring', 'text', null, false, 'Zum Beispiel nie, 1x pro Woche, hartes Sparring vermeiden.'),
                $this->field('competition_date', 'Wettkampf / Prüfung', 'text', null, false, 'Falls ein Termin geplant ist.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Kopf, Schulter, Knie, Rücken, Kontakt vermeiden usw.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Di, Do, Sa.'),
            ],
            'endurance' => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann trainierst du diese Ausdauersportart regelmäßig?'),
                $this->field('weekly_hours', 'Trainingsstunden pro Woche', 'number', 'h', true, 'Wie viele Stunden trainierst du aktuell pro Woche?'),
                $this->field('longest_session_minutes', 'Längste Einheit aktuell', 'number', 'min', true, 'Die längste Einheit der letzten 4 Wochen.'),
                $this->field('primary_disciplines', 'Disziplinen / Schwerpunkte', 'text', null, true, 'Zum Beispiel Triathlon: Schwimmen, Rad, Laufen; Rudern: Technik + Ausdauer.'),
                $this->field('race_goal', 'Ziel / Event', 'text', null, true, 'Zieldistanz, Eventdatum oder Leistungsziel.'),
                $this->field('weekly_elevation_m', 'Höhenmeter pro Woche', 'number', 'm', false, 'Optional, falls Anstiege eine Rolle spielen.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Belastung, Schmerzen, Technikprobleme, Reha.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel Mo, Mi, Fr, So.'),
            ],
            'mobility' => [
                $this->field('mobility_goal', 'Beweglichkeitsziel', 'textarea', null, true, 'Zum Beispiel Hüfte, Rücken, Schulter, Entspannung.'),
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann trainierst du Mobility, Yoga oder Beweglichkeit?'),
                $this->field('current_frequency', 'Aktueller Umfang', 'text', null, true, 'Zum Beispiel täglich 15 Minuten oder 3x pro Woche.'),
                $this->field('pain_areas', 'Schmerzbereiche', 'textarea', null, true, 'Bereiche, die vorsichtig behandelt werden müssen.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Zum Beispiel täglich 15 Minuten.'),
            ],
            default => [
                $this->field('experience', 'Erfahrung', 'date', null, true, 'Seit wann trainierst du diese Sportart?'),
                $this->field('current_volume', 'Aktueller Umfang', 'text', null, true, 'Aktuelle Dauer, Häufigkeit oder Menge pro Woche.'),
                $this->field('performance_reference', 'Leistungsreferenz', 'text', null, true, 'Bestzeit, Gewicht, Level, Score oder eine andere messbare Referenz.'),
                $this->field('injuries', 'Verletzungen / Einschränkungen', 'textarea', null, true, 'Alles, was der Plan vermeiden oder beachten soll.'),
                $this->field('available_days', 'Verfügbare Trainingstage', 'text', null, true, 'Wann kannst du realistisch trainieren?'),
            ],
        };
    }

    public function groupForSport(string $sportType): string
    {
        $value = str_replace(['_', ' '], '-', Str::ascii(Str::lower(trim($sportType))));

        return match (true) {
            in_array($value, ['laufen', 'lauf', 'laufsport', 'leichtathletik', 'run', 'running', 'joggen', 'jogging', 'strassenlauf', 'crosslauf', 'trailrunning', 'trail-running'], true) => 'running',
            in_array($value, ['gym', 'fitness', 'krafttraining', 'kraftsport', 'strength', 'calisthenics', 'gewichtheben', 'powerlifting', 'bodybuilding', 'body-building', 'bodybuilding-und-fitness', 'armwrestling'], true) => 'strength',
            in_array($value, ['cycling', 'radfahren', 'radsport', 'bike', 'biking', 'mountainbike', 'rennrad', 'gravel', 'bmx-racing', 'bmx-freestyle', 'bahnradsport', 'strassenradsport', 'straßenradsport', 'virtuelles-radfahren'], true) => 'cycling',
            in_array($value, ['schwimmen', 'swim', 'swimming', 'freiwasserschwimmen', 'rettungssport', 'unterwassersport'], true) => 'swimming',
            in_array($value, ['fussball', 'fuball', 'football', 'soccer', 'beach-soccer', 'futsal', 'basketball', '3x3-basketball', 'handball', 'volleyball', 'beachvolleyball', 'hockey', 'eishockey', 'bandy', 'floorball', 'netball', 'korfball', 'cricket', 'baseball', 'softball', 'lacrosse', 'polo', 'rugby-union', 'rugby-league', 'american-football', 'flag-football', 'australian-football', 'wasserball', 'ultimate-frisbee', 'flying-disc', 'fistball', 'faustball', 'sepak-takraw', 'kabaddi'], true) => 'team',
            in_array($value, ['tennis', 'tischtennis', 'badminton', 'squash', 'racquetball', 'padel', 'pickleball', 'beach-tennis', 'basque-pelota', 'teqball'], true) => 'racket',
            in_array($value, ['boxen', 'judo', 'ringen', 'taekwondo', 'karate', 'kickboxen', 'muay-thai', 'sambo', 'sumo', 'wushu', 'ju-jitsu', 'aikido', 'savate', 'fechten'], true) => 'combat',
            in_array($value, ['triathlon', 'moderner-fuenfkampf', 'moderner-funfkampf', 'rudern', 'kanu-rennsport', 'kanu-slalom', 'skilanglauf', 'ski-bergsteigen', 'orienteering', 'inline-speedskating', 'drachenboot'], true) => 'endurance',
            in_array($value, ['yoga', 'mobility', 'pilates', 'stretching', 'turnen', 'kunstturnen', 'rhythmische-sportgymnastik', 'trampolinturnen', 'parkour', 'klettern', 'dance-sport', 'breaking', 'cheerleading'], true) => 'mobility',
            default => 'generic',
        };
    }

    private function readinessFromMetrics(array $metrics, array $fields, bool $hasProfile): array
    {
        $requiredFields = collect($fields)->where('required', true)->values();
        $unknownKeys = collect($metrics['_unknown_fields'] ?? [])
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();
        $missing = $requiredFields
            ->reject(fn (array $field) => $unknownKeys->contains($field['key']))
            ->filter(fn (array $field) => blank($metrics[$field['key']] ?? null))
            ->map(fn (array $field) => $this->readinessFieldPayload($field))
            ->values()
            ->all();
        $unknown = $requiredFields
            ->filter(fn (array $field) => $unknownKeys->contains($field['key']))
            ->map(fn (array $field) => [
                ...$this->readinessFieldPayload($field),
                'unknown' => true,
            ])
            ->values()
            ->all();
        $requiredCount = max(1, $requiredFields->count());
        $completedCount = $requiredCount - count($missing) - count($unknown);
        $notReadyFields = [...$missing, ...$unknown];

        return [
            'ready' => $hasProfile && $notReadyFields === [],
            'score' => $hasProfile ? (int) round(($completedCount / $requiredCount) * 100) : 0,
            'missing' => $hasProfile ? $missing : $requiredFields
                ->map(fn (array $field) => $this->readinessFieldPayload($field))
                ->values()
                ->all(),
            'unknown' => $hasProfile ? $unknown : [],
        ];
    }

    private function sanitizeMetrics(array $metrics, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(function (array $field) use ($metrics) {
                $value = $metrics[$field['key']] ?? null;

                if ($value === null || $value === '') {
                    return [$field['key'] => null];
                }

                if (($field['type'] ?? 'text') === 'number') {
                    return [$field['key'] => round((float) str_replace(',', '.', (string) $value), 2)];
                }

                return [$field['key'] => Str::limit(trim((string) $value), 1000, '')];
            })
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function privateMetricsForAi(UserSport $profile): array
    {
        $metrics = collect($profile->performance_metrics ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if (isset($metrics['experience']) && is_string($metrics['experience'])) {
            $months = $this->monthsSinceDate($metrics['experience']);

            if ($months !== null) {
                $metrics['experience_months'] = $months;
                $metrics['experience_label'] = $this->experienceLabelFromMonths($months);
            }
        }

        foreach (['available_days', 'training_days'] as $dayKey) {
            if (isset($metrics[$dayKey]) && is_string($metrics[$dayKey])) {
                $daysLabel = $this->trainingDaysLabel($metrics[$dayKey]);

                if ($daysLabel !== null) {
                    $metrics[$dayKey.'_label'] = $daysLabel;
                }
            }
        }

        return $metrics;
    }

    private function readinessFieldPayload(array $field): array
    {
        return [
            'key' => $field['key'],
            'label' => $field['label'],
            'help' => $field['help'] ?? null,
        ];
    }

    private function trainingDaysLabel(string $value): ?string
    {
        $aliases = [
            'monday' => ['monday', 'mon', 'mo', 'montag', 'lundi', 'lun'],
            'tuesday' => ['tuesday', 'tue', 'di', 'dienstag', 'mardi', 'mar'],
            'wednesday' => ['wednesday', 'wed', 'mi', 'mittwoch', 'mercredi', 'mer'],
            'thursday' => ['thursday', 'thu', 'do', 'donnerstag', 'jeudi', 'jeu'],
            'friday' => ['friday', 'fri', 'fr', 'freitag', 'vendredi', 'ven'],
            'saturday' => ['saturday', 'sat', 'sa', 'samstag', 'samedi', 'sam'],
            'sunday' => ['sunday', 'sun', 'so', 'sonntag', 'dimanche', 'dim'],
        ];
        $labels = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
        $normalizedValue = str_replace([',', ';', '|', '/', '+'], ' ', Str::ascii(Str::lower($value)));
        $normalized = collect(preg_split('/\s+/', $normalizedValue) ?: [])->filter()->values();
        $selected = collect();

        if ($normalized->contains(fn (string $token) => in_array($token, ['weekend', 'weekends', 'wochenende'], true))) {
            $selected->push('saturday', 'sunday');
        }

        foreach ($aliases as $key => $dayAliases) {
            if ($normalized->contains(fn (string $token) => in_array($token, $dayAliases, true))) {
                $selected->push($key);
            }
        }

        $ordered = collect(array_keys($labels))
            ->filter(fn (string $key) => $selected->contains($key))
            ->values();

        if ($ordered->isEmpty()) {
            return null;
        }

        return $ordered
            ->map(fn (string $key) => $labels[$key])
            ->implode(', ');
    }

    private function monthsSinceDate(string $date): ?int
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $today = now()->startOfDay();

        if ($start->greaterThan($today)) {
            return 0;
        }

        return (int) $start->diffInMonths($today);
    }

    private function experienceLabelFromMonths(int $months): string
    {
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;
        $parts = [];

        if ($years > 0) {
            $parts[] = $years.' '.($years === 1 ? 'Jahr' : 'Jahre');
        }

        if ($remainingMonths > 0) {
            $parts[] = $remainingMonths.' '.($remainingMonths === 1 ? 'Monat' : 'Monate');
        }

        return ($parts === [] ? 'weniger als 1 Monat' : implode(' und ', $parts)).' Erfahrung';
    }

    private function resolveSport(string $sportType): ?Sport
    {
        $value = Str::lower(trim($sportType));
        $ascii = Str::ascii($value);
        $candidates = collect([$value, $ascii])
            ->merge(match ($this->groupForSport($sportType)) {
                'running' => ['laufen', 'lauf', 'laufsport', 'leichtathletik', 'running', 'run', 'strassenlauf', 'crosslauf', 'trailrunning'],
                'strength' => ['gym', 'fitness', 'krafttraining', 'kraftsport', 'bodybuilding', 'bodybuilding-und-fitness', 'powerlifting', 'gewichtheben'],
                'cycling' => ['cycling', 'radfahren', 'radsport', 'strassenradsport'],
                'swimming' => ['schwimmen', 'swimming'],
                'team' => ['fussball', 'fußball', 'football', 'soccer'],
                'racket' => ['tennis', 'tischtennis', 'badminton', 'squash', 'padel'],
                'combat' => ['boxen', 'judo', 'ringen', 'taekwondo', 'karate', 'kickboxen'],
                'endurance' => ['triathlon', 'rudern', 'kanu-rennsport', 'skilanglauf'],
                'mobility' => ['yoga', 'mobility'],
                default => [],
            })
            ->merge(match ($this->groupForSport($sportType)) {
                'running' => ['joggen', 'jogging'],
                'strength' => ['strength', 'calisthenics', 'body-building', 'armwrestling'],
                'cycling' => ['bike', 'biking', 'mountainbike', 'rennrad', 'gravel', 'bahnradsport', 'bmx-racing', 'bmx-freestyle', 'virtuelles-radfahren'],
                'swimming' => ['swim', 'freiwasserschwimmen', 'rettungssport', 'unterwassersport'],
                'team' => ['basketball', '3x3-basketball', 'handball', 'volleyball', 'beachvolleyball', 'futsal', 'hockey', 'eishockey', 'rugby-union', 'rugby-league', 'american-football', 'flag-football', 'australian-football', 'wasserball', 'cricket', 'baseball', 'softball', 'lacrosse', 'ultimate-frisbee', 'flying-disc'],
                'racket' => ['racquetball', 'pickleball', 'beach-tennis', 'basque-pelota', 'teqball'],
                'combat' => ['muay-thai', 'sambo', 'sumo', 'wushu', 'ju-jitsu', 'aikido', 'savate', 'fechten'],
                'endurance' => ['moderner-fuenfkampf', 'moderner-funfkampf', 'kanu-slalom', 'ski-bergsteigen', 'orienteering', 'inline-speedskating', 'drachenboot'],
                'mobility' => ['pilates', 'stretching', 'turnen', 'kunstturnen', 'rhythmische-sportgymnastik', 'trampolinturnen', 'parkour', 'klettern', 'dance-sport', 'breaking', 'cheerleading'],
                default => [],
            })
            ->filter()
            ->unique()
            ->values();

        return Sport::query()
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query
                        ->orWhereRaw('LOWER(slug) = ?', [$candidate])
                        ->orWhereRaw('LOWER(name) = ?', [$candidate]);
                }
            })
            ->first();
    }

    private function field(string $key, string $label, string $type, ?string $unit, bool $required, string $help): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'unit' => $unit,
            'required' => $required,
            'help' => $help,
            'default_visibility' => 'private',
        ];
    }
}
