<?php

return [
    'summary' => [
        'empty' => 'Ta journée est encore ouverte. Commence par l’entraînement, la nutrition ou l’eau.',
        'training' => ':minutes minutes d’entraînement',
        'calories' => ':calories kcal',
        'water' => ':water ml d’eau',
        'notifications' => ':count notifications',
    ],
    'coach' => [
        'next_training' => 'Ta prochaine séance est planifiée. Documente-la juste après pour garder une charge et une progression fiables.',
        'event' => 'Tu as un rendez-vous aujourd’hui. Garde une alimentation et une hydratation stables pour bien démarrer.',
        'start' => 'Planifie aujourd’hui une petite séance réaliste. Même 20 à 30 minutes entretiennent ton rythme.',
        'water' => 'L’entraînement est terminé. Hydrate-toi maintenant et soutiens ta récupération.',
        'meal' => 'L’entraînement et l’eau sont enregistrés. Ajoute un repas pour compléter la journée.',
        'complete' => 'Ta journée est bien remplie. Ajoute seulement un retour, un parcours ou des notes si nécessaire.',
    ],
    'steps' => [
        'training' => ['title' => 'Entraînement', 'documented' => 'Entraînement documenté aujourd’hui', 'plan' => 'Planifier une courte séance', 'goal' => 'Objectif : :minutes min', 'completed' => ':minutes min · :distance', 'document' => 'Documenter', 'start' => 'Démarrer'],
        'route' => ['title' => 'Parcours', 'plan' => 'Planifier un nouveau parcours', 'meta' => 'GPX, suivi, carte sportive', 'open' => 'Ouvrir le parcours', 'create' => 'Planifier'],
        'nutrition' => ['title' => 'Nutrition', 'meals' => ':count repas aujourd’hui', 'meta' => ':current / :target kcal', 'cta' => 'Ajouter'],
        'hydration' => ['title' => 'Eau', 'body' => ':current ml consommés', 'meta' => 'Objectif quotidien : :target ml', 'cta' => 'Ajouter de l’eau'],
        'reminders' => ['title' => 'Rappels', 'unread' => ':count notifications non lues', 'none' => 'Aucun rendez-vous ouvert', 'meta' => 'Boîte de réception et calendrier', 'event_cta' => 'Voir le rendez-vous', 'inbox_cta' => 'Ouvrir la boîte'],
    ],
    'actions' => [
        'catch_up_training' => ['label' => 'Rattraper la séance', 'reason' => 'Une séance planifiée cette semaine reste ouverte.'],
        'complete_training' => ['label' => 'Démarrer la séance du jour', 'reason' => 'La séance prévue aujourd’hui est prioritaire.'],
        'hydrate' => ['label' => 'Ajouter de l’eau', 'reason' => 'Ton hydratation reste sous 60 % après l’entraînement.'],
        'event' => ['label' => 'Préparer le rendez-vous', 'reason' => 'Ton prochain rendez-vous a lieu aujourd’hui.'],
        'meal' => ['label' => 'Enregistrer un repas', 'reason' => 'Aucun repas n’est encore documenté aujourd’hui.'],
        'start_training' => ['label' => 'Démarrer l’entraînement', 'reason' => 'Une courte séance entretient ta régularité hebdomadaire.'],
        'review' => ['label' => 'Vérifier la semaine', 'reason' => 'Ta journée est dans le plan. Vérifie ta progression et les retours.'],
    ],
];
