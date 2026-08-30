<?php

return [
    'errors' => [
        'not_joinable' => 'This challenge can no longer be joined.',
        'invitation_required' => 'An invitation is required for this private challenge.',
        'participant_required' => 'You must participate in the challenge.',
        'not_active' => 'This challenge is not active.',
        'automatic_only' => 'This challenge is tracked automatically only.',
        'invalid_date' => 'The date is outside the challenge period.',
        'public_forbidden' => 'Only admins and authorized staff may create public challenges.',
        'club_forbidden' => 'You may not create a challenge for this club.',
        'team_forbidden' => 'You may not create a challenge for this team.',
        'invitee_forbidden' => 'This person cannot be invited to the private challenge.',
    ],
    'notifications' => [
        'invitation_title' => 'New challenge invitation',
        'invitation_body' => ':user invited you to “:challenge”.',
        'accepted_title' => 'Challenge accepted',
        'accepted_body' => ':user joined “:challenge”.',
        'declined_title' => 'Challenge declined',
        'declined_body' => ':user declined “:challenge”.',
        'comment_title' => 'New challenge comment',
        'comment_body' => ':user commented on “:challenge”.',
    ],
];
