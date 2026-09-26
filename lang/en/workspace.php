<?php

return [
    'status' => [
        'personal' => 'Personal',
        'active' => 'Active',
        'foundation_available' => 'Core available',
        'training_active' => 'Training active',
        'notifications_active' => 'Notifications active',
    ],
    'items' => [
        'athlete' => ['title' => 'My sport', 'description' => 'Feed, training, events, nutrition, sport map and suitable training partners.'],
        'coach' => ['title' => 'Coach workspace', 'description' => 'Today’s tasks, teams, training plans, attendance and feedback.'],
        'club' => ['title' => 'Club administration', 'description' => 'Members, teams, invoices, sponsors and club communication.'],
        'sponsor' => ['title' => 'Sponsor cockpit', 'description' => 'Manage partnerships, campaigns, visibility, offers and impact in one place.'],
        'guardian' => ['title' => 'Parents & guardians', 'description' => 'Child profiles, consent, safety and access to relevant club information.'],
        'analytics' => ['title' => 'Analytics & performance', 'description' => 'Performance data, training, feedback and development reports.'],
        'medical' => ['title' => 'Medical & recovery', 'description' => 'Load, rehabilitation guidance, clearances and feedback in the training context.'],
        'media' => ['title' => 'Media & content', 'description' => 'Posts, media, SEO, club news and public communication.'],
        'support' => ['title' => 'Support', 'description' => 'User support, notifications and escalations.'],
    ],
    'work_items' => [
        'created' => 'Work item created.',
        'updated' => 'Work item updated.',
        'deleted' => 'Work item deleted.',
        'club_users_only' => 'Assignees and watchers must belong to this club.',
        'club_dependencies_only' => 'Dependencies must belong to this club.',
        'invalid_dependency' => 'A work item cannot depend on itself.',
        'invalid_parent' => 'A work item cannot be its own parent.',
    ],
];
