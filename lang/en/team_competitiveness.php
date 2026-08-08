<?php

return [
    'errors' => [
        'member_not_found' => 'Member not found.',
        'no_changes' => 'No changes were detected.',
    ],
    'responses' => [
        'role_updated' => 'Team role updated.',
        'fee_created' => 'Team fee recorded.',
        'fee_updated' => 'Team fee updated.',
        'fee_removed' => 'Team fee removed.',
    ],
    'organizer' => [
        'open_fees' => 'Resolve open team fees',
    ],
    'polls' => [
        'attendance_confirmation' => 'Confirm final attendance',
        'transport_options' => 'Coordinate shared rides',
        'equipment_needed' => 'Clarify equipment needs',
    ],
    'tasks' => [
        'remind_missing_responses' => 'Remind members who have not responded',
        'check_availability' => 'Check squad and availability',
        'review_open_fees' => 'Review open fees',
        'complete_roles' => 'Complete team roles',
    ],
    'materials' => [
        'balls' => 'Balls',
        'first_aid' => 'First aid',
        'water' => 'Water',
        'jerseys' => 'Jerseys',
    ],
    'briefing' => [
        'critical' => 'The response deadline has passed and replies are missing.',
        'high' => 'Too few members have confirmed the next event.',
        'watch' => 'Monitor the response rate and send an early reminder.',
        'stable' => 'Day-to-day team operations look stable.',
        'summary_missing' => ':count members have not responded; confirmation rate: :rate%.',
        'summary_clear' => 'No attendance replies are outstanding; confirmation rate: :rate%.',
    ],
];
