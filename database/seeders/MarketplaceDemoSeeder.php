<?php

namespace Database\Seeders;

use App\Models\MarketplaceProduct;
use Illuminate\Database\Seeder;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['Laufschuhe TempoFlex Pro', 'Leichter Trainingsschuh fuer Strasse, Bahn und Intervalltraining mit stabiler Daempfung.', 'product', 8990, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80'],
            ['Athletikband Set 5-teilig', 'Widerstandsbaender fuer Warm-up, Reha, Krafttraining und Mobility im Vereinstraining.', 'product', 2490, 'https://images.unsplash.com/photo-1599058917765-a780eda07a3e?auto=format&fit=crop&w=1200&q=80'],
            ['Torwart-Handschuhe Grip Max', 'Trainings- und Spielhandschuhe mit starkem Grip fuer Jugend und Erwachsene.', 'product', 3990, 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80'],
            ['GPS Laufanalyse Online', 'Individuelle Analyse deiner Laufdaten mit Technikhinweisen und Trainingsvorschlaegen.', 'service', 5900, 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80'],
            ['8 Wochen 10-km Trainingsplan', 'Strukturierter Plan mit Belastungssteuerung, Regeneration und Zieltempo-Einheiten.', 'course', 4900, 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?auto=format&fit=crop&w=1200&q=80'],
            ['Sommer-Fussballcamp U12', 'Drei Tage Technik, Koordination, Spielformen und Team-Challenges fuer Nachwuchsspieler.', 'camp', 12900, 'https://images.unsplash.com/photo-1526232761682-d26e03ac148e?auto=format&fit=crop&w=1200&q=80'],
            ['Yoga Mobility fuer Sportler', 'Online-Kurs fuer Beweglichkeit, Stabilitaet und schnellere Regeneration.', 'course', 2900, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=1200&q=80'],
            ['Vereins-Trikotsatz Basic', 'Robuster Trikotsatz fuer Teams inklusive Nummernoption und sportlicher Passform.', 'product', 29900, 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1200&q=80'],
            ['Basketball Skills Clinic', 'Intensiver Tagesworkshop fuer Ballhandling, Wurfmechanik und Defense-Grundlagen.', 'camp', 7900, 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1200&q=80'],
            ['Ernaehrungsberatung Wettkampf', 'Individuelle Beratung fuer Trainingstage, Wettkampftag und Regenerationsfenster.', 'service', 6900, 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=1200&q=80'],
            ['Schwimmtechnik Videoanalyse', 'Technikanalyse fuer Kraul und Ruecken mit konkreten Korrekturuebungen.', 'service', 7500, 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=1200&q=80'],
            ['Faszienrolle Recovery Pack', 'Set aus Rolle und Ball fuer Regeneration, Mobility und Muskelpflege nach dem Training.', 'product', 3490, 'https://images.unsplash.com/photo-1571019613914-85f342c6a11e?auto=format&fit=crop&w=1200&q=80'],
        ];

        foreach ($products as [$title, $description, $category, $price, $imageUrl]) {
            MarketplaceProduct::query()->updateOrCreate(
                ['title' => $title],
                [
                    'description' => $description,
                    'image_url' => $imageUrl,
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
