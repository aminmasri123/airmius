<?php

return [
    'actions' => [
        'confirm_attendance' => ['label' => 'Respond to attendance', 'reason' => 'Your response for the next team event is still missing.'],
        'remind_missing_responses' => ['label' => 'Collect responses', 'reason' => ':count team members have not responded yet.'],
        'check_squad_availability' => ['label' => 'Check squad availability', 'reason' => 'Only a few confirmations are available for the next event.'],
        'organize_carpool' => ['label' => 'Organize a carpool', 'reason' => 'No free seats are visible for the next event yet.'],
        'collect_open_fees' => ['label' => 'Review open fees', 'reason' => ':count team fees are still open.'],
        'review_own_fee' => ['label' => 'Review your fee', 'reason' => 'You still have :count open fees.'],
        'complete_parent_links' => ['label' => 'Complete parent links', 'reason' => ':count minor members still have no linked contact.'],
        'extend_season_calendar' => ['label' => 'Extend team calendar', 'reason' => 'The upcoming team calendar currently contains only :count events.'],
        'team_routine_stable' => ['label' => 'Open team overview', 'reason' => 'There is currently no urgent open action for the team.'],
    ],
];
