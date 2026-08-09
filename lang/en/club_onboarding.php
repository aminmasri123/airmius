<?php

return [
    'title' => 'Get your club ready',
    'subtitle' => 'Progress is calculated automatically from real club, team and membership data.',
    'progress' => ':completed of :total steps complete',
    'complete' => 'Complete',
    'open' => 'Open',
    'steps' => [
        'profile' => ['title' => 'Complete the club profile', 'description' => 'Sport, location and country provide a reliable foundation.'],
        'verification' => ['title' => 'Verify the club', 'description' => 'Verification builds trust and improves public discoverability.'],
        'roles' => ['title' => 'Secure leadership coverage', 'description' => 'At least two responsible people avoid dependency on one person.'],
        'team' => ['title' => 'Create the first team', 'description' => 'Teams connect members, training and events.'],
        'membership' => ['title' => 'Configure memberships', 'description' => 'Enable applications and at least one membership type.'],
        'members' => ['title' => 'Add members', 'description' => 'Invite another member or import existing records.'],
        'event' => ['title' => 'Plan the first event', 'description' => 'A training, match or club event activates the calendar.'],
        'communication' => ['title' => 'Publish the first update', 'description' => 'A post or announcement starts club communication.'],
        'documents' => ['title' => 'Store the first document', 'description' => 'Add an approved club document to the protected file area.'],
    ],
    'actions' => [
        'profile' => 'Open profile', 'verification' => 'Open verification', 'roles' => 'Manage roles',
        'teams' => 'Open teams', 'memberships' => 'Set up memberships', 'members' => 'Add members',
        'events' => 'Plan event', 'feed' => 'Create update', 'files' => 'Store document',
    ],
];
