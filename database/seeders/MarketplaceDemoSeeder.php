<?php

namespace Database\Seeders;

use App\Models\MarketplaceProduct;
use Illuminate\Database\Seeder;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['Laufschuhe TempoFlex Pro', 'Leichter Trainingsschuh fuer Strasse, Bahn und Intervalltraining mit stabiler Daempfung.', 'product', 8990],
            ['Athletikband Set 5-teilig', 'Widerstandsbaender fuer Warm-up, Reha, Krafttraining und Mobility im Vereinstraining.', 'product', 2490],
            ['Torwart-Handschuhe Grip Max', 'Trainings- und Spielhandschuhe mit starkem Grip fuer Jugend und Erwachsene.', 'product', 3990],
            ['GPS Laufanalyse Online', 'Individuelle Analyse deiner Laufdaten mit Technikhinweisen und Trainingsvorschlaegen.', 'service', 5900],
            ['8 Wochen 10-km Trainingsplan', 'Strukturierter Plan mit Belastungssteuerung, Regeneration und Zieltempo-Einheiten.', 'course', 4900],
            ['Sommer-Fussballcamp U12', 'Drei Tage Technik, Koordination, Spielformen und Team-Challenges fuer Nachwuchsspieler.', 'camp', 12900],
            ['Yoga Mobility fuer Sportler', 'Online-Kurs fuer Beweglichkeit, Stabilitaet und schnellere Regeneration.', 'course', 2900],
            ['Vereins-Trikotsatz Basic', 'Robuster Trikotsatz fuer Teams inklusive Nummernoption und sportlicher Passform.', 'product', 29900],
            ['Basketball Skills Clinic', 'Intensiver Tagesworkshop fuer Ballhandling, Wurfmechanik und Defense-Grundlagen.', 'camp', 7900],
            ['Ernaehrungsberatung Wettkampf', 'Individuelle Beratung fuer Trainingstage, Wettkampftag und Regenerationsfenster.', 'service', 6900],
            ['Schwimmtechnik Videoanalyse', 'Technikanalyse fuer Kraul und Ruecken mit konkreten Korrekturuebungen.', 'service', 7500],
            ['Faszienrolle Recovery Pack', 'Set aus Rolle und Ball fuer Regeneration, Mobility und Muskelpflege nach dem Training.', 'product', 3490],
        ];

        foreach ($products as [$title, $description, $category, $price]) {
            MarketplaceProduct::query()->updateOrCreate(
                ['title' => $title],
                [
                    'description' => $description,
                    'category' => $category,
                    'price_cents' => $price,
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
