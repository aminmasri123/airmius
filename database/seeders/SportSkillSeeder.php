<?php

namespace Database\Seeders;

use App\Models\Sport;
use App\Models\SportSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportSkillSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'team' => [
                ['teamplay', 'Teamplay', 'Kommunikation, Zusammenspiel und verlässliches Verhalten in Gruppen.'],
                ['game_understanding', 'Spielverständnis', 'Situationen lesen, Entscheidungen treffen und Taktik verstehen.'],
                ['technique', 'Technik', 'Sportartspezifische Grundtechniken sauber und kontrolliert ausführen.'],
                ['stamina', 'Ausdauer', 'Belastungen über Training oder Wettkampf hinweg stabil halten.'],
                ['fair_play', 'Fairplay', 'Respektvoll, regelbewusst und teamorientiert handeln.'],
            ],
            'endurance' => [
                ['endurance', 'Ausdauer', 'Belastung über längere Zeiträume kontrolliert halten.'],
                ['pace_control', 'Tempo-Kontrolle', 'Rhythmus, Intensität und Erholung passend steuern.'],
                ['technique_efficiency', 'Technik-Effizienz', 'Bewegungen ökonomisch und sauber ausführen.'],
                ['consistency', 'Konstanz', 'Regelmäßig trainieren und Fortschritt langfristig aufbauen.'],
                ['competition_readiness', 'Wettkampfbereitschaft', 'Vorbereitung, Fokus und Belastungssteuerung am Wettkampftag.'],
            ],
            'combat' => [
                ['body_control', 'Körperkontrolle', 'Balance, Koordination und saubere Bewegungsführung.'],
                ['technique', 'Technik', 'Grundtechniken sicher, präzise und regelkonform anwenden.'],
                ['reaction', 'Reaktion', 'Situationen schnell erkennen und angemessen reagieren.'],
                ['discipline', 'Disziplin', 'Respekt, Trainingshaltung und sichere Ausführung.'],
                ['rule_understanding', 'Regelverständnis', 'Regeln, Grenzen und Sicherheitsaspekte kennen.'],
            ],
            'precision' => [
                ['precision', 'Präzision', 'Ziele, Bewegungen oder Entscheidungen exakt ausführen.'],
                ['focus', 'Fokus', 'Konzentration auch unter Druck halten.'],
                ['technique', 'Technik', 'Abläufe kontrolliert und wiederholbar durchführen.'],
                ['strategy', 'Strategie', 'Planung, Risiko und Spielsituation bewusst steuern.'],
                ['composure', 'Ruhe', 'Stabil bleiben, wenn Ergebnisse knapp oder wechselhaft sind.'],
            ],
            'mind' => [
                ['strategy', 'Strategie', 'Langfristige Pläne entwickeln und anpassen.'],
                ['tactics', 'Taktik', 'Kurzfristige Chancen und Risiken erkennen.'],
                ['analysis', 'Analyse', 'Partien, Muster oder Fehler nachvollziehbar auswerten.'],
                ['focus', 'Fokus', 'Konzentration über längere Denkphasen halten.'],
                ['learning', 'Lernbereitschaft', 'Feedback aufnehmen und eigenes Spiel verbessern.'],
            ],
            'expression' => [
                ['rhythm', 'Rhythmus', 'Timing, Tempo und musikalische Struktur sicher halten.'],
                ['body_control', 'Körperkontrolle', 'Bewegungen sauber, bewusst und koordiniert ausführen.'],
                ['expression', 'Ausdruck', 'Präsenz, Emotion und Stil sichtbar machen.'],
                ['teamwork', 'Zusammenarbeit', 'Partner, Gruppe oder Formation aufmerksam wahrnehmen.'],
                ['stage_readiness', 'Auftrittssicherheit', 'Vor Publikum oder im Wettkampf stabil auftreten.'],
            ],
            'general' => [
                ['technique', 'Technik', 'Grundlagen der Sportart sicher und sauber ausführen.'],
                ['fitness', 'Fitness', 'Kraft, Beweglichkeit und Belastbarkeit passend entwickeln.'],
                ['discipline', 'Disziplin', 'Regelmäßig, respektvoll und konzentriert trainieren.'],
                ['learning', 'Lernbereitschaft', 'Feedback aufnehmen und Fortschritt reflektieren.'],
                ['fair_play', 'Fairplay', 'Respektvoll, regelbewusst und sportlich handeln.'],
            ],
        ];

        Sport::query()->orderBy('sort_order')->each(function (Sport $sport) use ($templates) {
            $group = $this->groupForSport($sport);

            foreach ($templates[$group] as $index => [$key, $name, $description]) {
                SportSkill::updateOrCreate(
                    [
                        'sport_id' => $sport->id,
                        'key' => $key,
                    ],
                    [
                        'name' => $name,
                        'description' => $description,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        });
    }

    private function groupForSport(Sport $sport): string
    {
        $slug = $sport->slug;

        $team = [
            'fussball', 'futsal', 'beach-soccer', 'american-football', 'flag-football',
            'australian-football', 'rugby-union', 'rugby-league', 'basketball',
            '3x3-basketball', 'volleyball', 'beachvolleyball', 'handball', 'hockey',
            'eishockey', 'bandy', 'floorball', 'netball', 'korfball', 'cricket',
            'baseball', 'softball', 'lacrosse', 'fistball', 'faustball', 'sepaktakraw',
            'kabaddi', 'ultimate-frisbee', 'flying-disc',
        ];
        $endurance = [
            'leichtathletik', 'strassenlauf', 'crosslauf', 'trailrunning', 'triathlon',
            'radsport', 'bmx-racing', 'bmx-freestyle', 'mountainbike', 'bahnradsport',
            'strassenradsport', 'schwimmen', 'freiwasserschwimmen', 'rudern',
            'kanu-rennsport', 'kanu-slalom', 'skilanglauf', 'biathlon',
        ];
        $combat = [
            'boxen', 'judo', 'ringen', 'taekwondo', 'karate', 'kickboxen', 'muay-thai',
            'sambo', 'sumo', 'wushu', 'ju-jitsu', 'aikido', 'savate', 'armwrestling',
        ];
        $precision = [
            'golf', 'minigolf', 'bogenschiessen', 'sportschiessen', 'bowling', 'darts',
            'billiard-sports', 'boules', 'curling', 'casting-sport',
        ];
        $mind = ['schach', 'bridge', 'damespiel', 'go'];
        $expression = [
            'dance-sport', 'breaking', 'cheerleading', 'kunstturnen',
            'rhythmische-sportgymnastik', 'trampolinturnen',
        ];

        return match (true) {
            in_array($slug, $team, true) => 'team',
            in_array($slug, $endurance, true) => 'endurance',
            in_array($slug, $combat, true) => 'combat',
            in_array($slug, $precision, true) => 'precision',
            in_array($slug, $mind, true) => 'mind',
            in_array($slug, $expression, true) => 'expression',
            Str::contains($slug, ['tanz', 'dance']) => 'expression',
            default => 'general',
        };
    }
}
