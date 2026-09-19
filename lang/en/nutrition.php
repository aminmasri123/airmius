<?php

return [
    'notifications' => [
        'water_title' => 'Time for a drink break',
        'water_body' => 'Would you like a drink or to log one?',
    ],
    'responses' => [
        'product_not_found' => 'No product was found for this barcode.',
        'ai_unavailable' => 'AI analysis is currently unavailable. Please try again later.',
        'ai_suggestion_created' => 'AI suggestion created. Review it before saving.',
        'goal_saved' => 'Nutrition goal saved.',
        'meal_saved' => 'Meal saved.',
        'meal_saved_named' => 'Meal “:meal” saved.',
        'water_saved' => 'Water intake saved.',
        'water_logged' => ':amount ml logged.',
        'meal_updated' => 'Meal updated.',
        'meal_deleted' => 'Meal deleted.',
    ],
    'suggestions' => [
        'strength' => [
            'title' => 'Protein after strength training',
            'body' => 'After “:training”, choose a protein-rich meal with carbohydrates.',
        ],
        'endurance' => [
            'title' => 'Energy for endurance',
            'body' => 'Around “:training”, easily digestible carbohydrates and enough water can help.',
        ],
        'recovery' => [
            'title' => 'Support recovery',
            'body' => 'After “:training”, protein, fluids and a simple meal are a good choice.',
        ],
        'daily_anchor' => [
            'title' => 'Daily anchor',
            'build_muscle' => 'No training detected today: Still plan three to five servings of protein throughout the day.',
            'fat_loss' => 'No training detected today: Choose filling meals with protein and vegetables.',
            'performance' => 'No training detected today: Keep carbohydrates available for your next session.',
            'default' => 'No training detected today: A simple, balanced meal is often enough.',
        ],
    ],
];
