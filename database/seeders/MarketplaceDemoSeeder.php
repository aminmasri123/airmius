<?php

namespace Database\Seeders;

use App\Models\MarketplaceProduct;
use Illuminate\Database\Seeder;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        MarketplaceProduct::query()
            ->whereIn('title', [
                'Laufschuhe TempoFlex Pro',
                'Athletikband Set 5-teilig',
                'Torwart-Handschuhe Grip Max',
                'GPS Laufanalyse Online',
                '8 Wochen 10-km Trainingsplan',
                'Sommer-Fussballcamp U12',
                'Yoga Mobility für Sportler',
                'Vereins-Trikotsatz Basic',
                'Basketball Skills Clinic',
                'Ernaehrungsberatung Wettkampf',
                'Schwimmtechnik Videoanalyse',
                'Faszienrolle Recovery Pack',
            ])
            ->delete();

        $sports = [
            ['Laufen', 'Runner', 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?auto=format&fit=crop&w=1200&q=80'],
            ['Fussball', 'Pitch', 'https://images.unsplash.com/photo-1526232761682-d26e03ac148e?auto=format&fit=crop&w=1200&q=80'],
            ['Basketball', 'Court', 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1200&q=80'],
            ['Schwimmen', 'Aqua', 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=1200&q=80'],
            ['Yoga', 'Flow', 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=1200&q=80'],
            ['Fitness', 'Power', 'https://images.unsplash.com/photo-1599058917765-a780eda07a3e?auto=format&fit=crop&w=1200&q=80'],
            ['Radsport', 'Velo', 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=1200&q=80'],
            ['Tennis', 'Ace', 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1200&q=80'],
            ['Volleyball', 'Block', 'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?auto=format&fit=crop&w=1200&q=80'],
            ['Handball', 'Arena', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80'],
            ['Boxen', 'Strike', 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=1200&q=80'],
            ['Klettern', 'Grip', 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1200&q=80'],
            ['Ski', 'Alpine', 'https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?auto=format&fit=crop&w=1200&q=80'],
            ['Tanzen', 'Move', 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?auto=format&fit=crop&w=1200&q=80'],
            ['Golf', 'Green', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1200&q=80'],
            ['Triathlon', 'Endurance', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80'],
            ['Badminton', 'Shuttle', 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=1200&q=80'],
            ['Tischtennis', 'Spin', 'https://images.unsplash.com/photo-1511067007398-7e4b90cfa4bc?auto=format&fit=crop&w=1200&q=80'],
            ['Rudern', 'Crew', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=80'],
            ['Leichtathletik', 'Track', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80'],
        ];

        $offers = [
            ['Performance Schuh', 'product', 8990, 'Leichter Trainingsschuh mit stabiler Daempfung für Technik, Tempo und Grundlageneinheiten.'],
            ['Trainingsshirt Set', 'product', 4490, 'Atmungsaktives Set für Vereinstraining, Warm-up und Wettkampfvorbereitung.'],
            ['Mobility Starter Pack', 'product', 2990, 'Kompaktes Set für Beweglichkeit, Aktivierung und Regeneration nach intensiven Einheiten.'],
            ['Coach Videoanalyse', 'service', 6900, 'Individuelle Analyse mit klaren Technikhinweisen und konkreten Uebungen für die naechste Einheit.'],
            ['Ernaehrungsberatung', 'service', 7900, 'Persoenliche Beratung für Trainingstage, Wettkampfplanung und Regenerationsfenster.'],
            ['8 Wochen Trainingsplan', 'course', 4900, 'Strukturierter Plan mit Belastungssteuerung, Progression und Zieltempo-Einheiten.'],
            ['Online Technik-Kurs', 'course', 5900, 'Digitaler Kurs mit Uebungsreihen, Korrekturpunkten und praktischen Wochenaufgaben.'],
            ['Feriencamp', 'camp', 12900, 'Mehrtaegiges Camp mit Technik, Koordination, Spielformen und Team-Challenges.'],
            ['Skills Clinic', 'camp', 7900, 'Intensiver Tagesworkshop für Grundlagen, Detailtechnik und spielnahe Anwendung.'],
            ['Recovery Bundle', 'product', 3490, 'Regenerationspaket für Muskelpflege, Mobility und aktive Erholung nach dem Training.'],
            ['Vereinsausstattung Basic', 'product', 29900, 'Robustes Ausstattungspaket für Teams inklusive Nummernoption und sportlicher Passform.'],
            ['Mentoring Session', 'service', 9900, 'Einzeltermin mit Zielanalyse, Trainingsfeedback und Prioritaeten für die naechsten Wochen.'],
            ['Einsteiger Kurs', 'course', 3900, 'Grundlagenkurs für neue Sportler mit sicherem Aufbau und einfachen Uebungsprogressionen.'],
            ['Elite Workshop', 'camp', 14900, 'Fortgeschrittener Workshop mit Wettkampffokus, Technikdetails und Belastungssteuerung.'],
            ['Sensorik Testpaket', 'service', 11900, 'Mess- und Auswertungspaket für Bewegungsqualitaet, Belastung und Trainingssteuerung.'],
            ['Team Challenge Kit', 'product', 5590, 'Materialpaket für Trainingsspiele, Gruppenaufgaben und motivierende Team-Challenges.'],
            ['Taktik Playbook', 'course', 4500, 'Praxisnahes Playbook mit Situationen, Entscheidungen und Coaching-Cues für Training und Spiel.'],
            ['Regenerationskurs', 'course', 3200, 'Kurs für Schlaf, Mobility, Erholung und alltagstaugliche Routinen rund um den Sport.'],
            ['Athletik Check-up', 'service', 8900, 'Analyse von Kraft, Beweglichkeit und Stabilitaet mit konkretem Trainingsfokus.'],
            ['Vereinscamp Wochenende', 'camp', 17900, 'Wochenendcamp für Teams mit Technikstationen, Athletik, Analyse und Abschlussturnier.'],
            ['Wettkampf Paket', 'product', 6990, 'Praktisches Paket für Wettkampftag, Warm-up, Organisation und kleine Materialreserven.'],
            ['Trainer Fortbildung', 'course', 12900, 'Fortbildung für Trainer mit Methodik, Belastungssteuerung und praktischen Uebungsformaten.'],
            ['Mentaltraining Session', 'service', 8500, 'Coaching für Fokus, Routinen, Wettkampfdruck und mentale Stabilitaet.'],
            ['Kids Starterset', 'product', 3990, 'Einsteigerfreundliches Materialset für Kindertraining, Spielstationen und koordinative Aufgaben.'],
            ['Pro Analyse Paket', 'service', 15900, 'Umfangreiche Analyse mit Auswertung, Feedbackgespraech und priorisiertem Trainingsplan.'],
        ];

        $variants = [
            ['Starter', 0.80, 'ideal für den Einstieg und kleine Trainingsgruppen'],
            ['Club', 1.00, 'abgestimmt auf regelmässig e Einheiten im Verein'],
            ['Pro', 1.25, 'mit mehr Tiefe für ambitionierte Sportler'],
            ['Elite', 1.55, 'für hohe Belastung und Wettkampffokus'],
            ['Junior', 0.75, 'angepasst für Nachwuchs und Schulgruppen'],
            ['Senior', 0.90, 'mit Fokus auf Stabilitaet und sauberen Aufbau'],
            ['Team', 1.35, 'für Gruppen, Mannschaften und gemeinsame Ziele'],
            ['Compact', 0.70, 'platzsparend, schnell einsetzbar und leicht zu organisieren'],
            ['Premium', 1.80, 'mit erweiterten Inhalten und hochwertiger Ausstattung'],
            ['Digital', 0.65, 'online nutzbar und flexibel in den Trainingsalltag integrierbar'],
        ];

        for ($i = 0; $i < 500; $i++) {
            [$sport, $brand, $imageUrl] = $sports[$i % count($sports)];
            [$offer, $category, $basePrice, $description] = $offers[intdiv($i, count($sports)) % count($offers)];
            [$variant, $factor, $variantText] = $variants[($i + intdiv($i, count($sports))) % count($variants)];

            $title = "{$sport} {$offer} {$brand} {$variant}";
            $price = (int) round($basePrice * $factor / 10) * 10;

            MarketplaceProduct::query()->updateOrCreate(
                ['title' => $title],
                [
                    'description' => "{$description} Diese {$variant}-Variante ist {$variantText}.",
                    'image_url' => $imageUrl,
                    'category' => $category,
                    'price_cents' => max(990, $price),
                    'currency' => 'EUR',
                    'status' => 'published',
                    'moderation_status' => 'approved',
                    'commission_percent' => 10,
                    'payout_status' => 'pending_sales',
                ],
            );
        }
    }
}
