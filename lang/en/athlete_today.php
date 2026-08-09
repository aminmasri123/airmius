<?php

return [
    'summary' => [
        'empty' => 'Your day is still open. Start with training, nutrition, or water.',
        'training' => ':minutes training minutes',
        'calories' => ':calories kcal',
        'water' => ':water ml water',
        'notifications' => ':count notifications',
    ],
    'coach' => [
        'next_training' => 'Your next session is planned. Log it right afterwards to keep load and progress accurate.',
        'event' => 'You have an event today. Keep nutrition and hydration steady so you start prepared.',
        'start' => 'Plan a small, realistic session today. Even 20 to 30 minutes keeps your rhythm active.',
        'water' => 'Training is done. Rehydrate now and support your recovery.',
        'meal' => 'Training and water are visible. Add a meal to complete today’s flow.',
        'complete' => 'Your day is well covered. Add feedback, a route, or notes only if needed.',
    ],
    'steps' => [
        'training' => ['title' => 'Training', 'documented' => 'Training logged today', 'plan' => 'Plan a short session today', 'goal' => ':minutes min goal', 'completed' => ':minutes min · :distance', 'document' => 'Log session', 'start' => 'Start training'],
        'route' => ['title' => 'Route', 'plan' => 'Plan a new route or course', 'meta' => 'GPX, tracking, sports map', 'open' => 'Open route', 'create' => 'Plan route'],
        'nutrition' => ['title' => 'Nutrition', 'meals' => ':count meals today', 'meta' => ':current / :target kcal', 'cta' => 'Add entry'],
        'hydration' => ['title' => 'Water', 'body' => ':current ml consumed', 'meta' => ':target ml daily goal', 'cta' => 'Log water'],
        'reminders' => ['title' => 'Reminders', 'unread' => ':count unread notifications', 'none' => 'No open events', 'meta' => 'Inbox & calendar', 'event_cta' => 'Open event', 'inbox_cta' => 'Open inbox'],
    ],
    'actions' => [
        'catch_up_training' => ['label' => 'Catch up session', 'reason' => 'A planned session from this week is still open.'],
        'complete_training' => ['label' => 'Start today’s session', 'reason' => 'Today’s planned session takes priority.'],
        'hydrate' => ['label' => 'Add water', 'reason' => 'Your hydration is still below 60% after training.'],
        'event' => ['label' => 'Prepare for event', 'reason' => 'Your next event takes place today.'],
        'meal' => ['label' => 'Log a meal', 'reason' => 'No meal has been logged today yet.'],
        'start_training' => ['label' => 'Start training', 'reason' => 'A short session keeps your weekly consistency active.'],
        'review' => ['label' => 'Review week', 'reason' => 'Your day is on track. Review progress and feedback.'],
    ],
];
