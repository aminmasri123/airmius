<?php

return [
    'responses' => [
        'product_not_found' => 'Für diesen Barcode wurde kein Produkt gefunden.',
        'ai_unavailable' => 'Die KI-Auswertung ist derzeit nicht verfügbar. Bitte versuche es später erneut.',
        'ai_suggestion_created' => 'KI-Vorschlag erstellt. Bitte prüfen und erst danach speichern.',
        'goal_saved' => 'Ernährungsziel gespeichert.',
        'meal_saved' => 'Mahlzeit gespeichert.',
        'meal_saved_named' => 'Mahlzeit „:meal“ wurde gespeichert.',
        'water_saved' => 'Trinken gespeichert.',
        'water_logged' => ':amount ml wurden eingetragen.',
        'meal_updated' => 'Mahlzeit wurde aktualisiert.',
        'meal_deleted' => 'Mahlzeit wurde gelöscht.',
    ],
    'suggestions' => [
        'strength' => [
            'title' => 'Protein nach Krafttraining',
            'body' => 'Nach „:training“ passt eine proteinreiche Mahlzeit mit Kohlenhydraten.',
        ],
        'endurance' => [
            'title' => 'Energie für Ausdauer',
            'body' => 'Rund um „:training“ helfen leicht verdauliche Kohlenhydrate und genug Wasser.',
        ],
        'recovery' => [
            'title' => 'Regeneration sichern',
            'body' => 'Nach „:training“ sind Protein, Flüssigkeit und eine einfache Mahlzeit sinnvoll.',
        ],
        'daily_anchor' => [
            'title' => 'Tagesanker',
            'build_muscle' => 'Heute kein Training erkannt: Plane trotzdem drei bis fünf Proteinportionen über den Tag.',
            'fat_loss' => 'Heute kein Training erkannt: Setze auf sättigende Mahlzeiten mit Protein und Gemüse.',
            'performance' => 'Heute kein Training erkannt: Halte deine Kohlenhydrate für die nächste Einheit bereit.',
            'default' => 'Heute kein Training erkannt: Eine einfache, ausgewogene Mahlzeit reicht oft schon.',
        ],
    ],
];
