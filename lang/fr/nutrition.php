<?php

return [
    'notifications' => [
        'water_title' => 'Une pause pour boire ?',
        'water_body' => 'Souhaites-tu boire quelque chose ou noter une boisson ?',
    ],
    'responses' => [
        'product_not_found' => 'Aucun produit n’a été trouvé pour ce code-barres.',
        'ai_unavailable' => 'L’analyse par IA est actuellement indisponible. Réessayez plus tard.',
        'ai_suggestion_created' => 'Suggestion IA créée. Vérifiez-la avant de l’enregistrer.',
        'goal_saved' => 'Objectif nutritionnel enregistré.',
        'meal_saved' => 'Repas enregistré.',
        'meal_saved_named' => 'Repas « :meal » enregistré.',
        'water_saved' => 'Consommation d’eau enregistrée.',
        'water_logged' => ':amount ml enregistrés.',
        'meal_updated' => 'Repas mis à jour.',
        'meal_deleted' => 'Repas supprimé.',
    ],
    'suggestions' => [
        'strength' => [
            'title' => 'Des protéines après la musculation',
            'body' => 'Après « :training », privilégie un repas riche en protéines avec des glucides.',
        ],
        'endurance' => [
            'title' => 'De l’énergie pour l’endurance',
            'body' => 'Autour de « :training », des glucides faciles à digérer et suffisamment d’eau peuvent aider.',
        ],
        'recovery' => [
            'title' => 'Favoriser la récupération',
            'body' => 'Après « :training », des protéines, des liquides et un repas simple sont un bon choix.',
        ],
        'daily_anchor' => [
            'title' => 'Repère du jour',
            'build_muscle' => 'Aucun entraînement détecté aujourd’hui : prévois tout de même trois à cinq portions de protéines dans la journée.',
            'fat_loss' => 'Aucun entraînement détecté aujourd’hui : choisis des repas rassasiants avec des protéines et des légumes.',
            'performance' => 'Aucun entraînement détecté aujourd’hui : garde des glucides disponibles pour ta prochaine séance.',
            'default' => 'Aucun entraînement détecté aujourd’hui : un repas simple et équilibré suffit souvent.',
        ],
    ],
];
