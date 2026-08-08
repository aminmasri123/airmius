<?php

return [
    'rules' => [
        'inactive' => ['month' => '12 Monate', 'title' => 'Als inaktiv markieren', 'description' => 'Nutzer bleibt erhalten, wird aber im Adminbereich als inaktiv erkennbar.'],
        'reactivation' => ['month' => '18 Monate', 'title' => 'Reaktivierungs-Mail senden', 'description' => 'Automatisch oder manuell erinnern und den Versand in der Mail-Zentrale protokollieren.'],
        'hidden' => ['month' => '24 Monate', 'title' => 'Profil ausblenden', 'description' => 'Öffentliches Profil, Suchbarkeit und Komfort-Kommunikation stoppen.'],
        'anonymized' => ['month' => '36 Monate', 'title' => 'Anonymisieren', 'description' => 'Nicht notwendige personenbezogene Daten entfernen oder anonymisieren.'],
        'archived' => ['month' => 'Pflichtdaten', 'title' => 'Separat archivieren', 'description' => 'Rechnungen, Zahlungen und Vertragsdaten bleiben gemäß Aufbewahrungspflichten erhalten.'],
    ],
];
