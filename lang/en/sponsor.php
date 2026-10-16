<?php

return [
    'flash' => [
        'profile_saved' => 'Sponsor profile saved. The public details are now being reviewed.',
        'created' => 'Sponsor created.',
        'updated' => 'Sponsor updated.',
        'deleted' => 'Sponsor deleted.',
    ],
    'validation' => [
        'verification_incomplete' => 'The sponsor profile is not ready for verification (:count required items remain).',
    ],
    'notifications' => [
        'review_requested_title' => 'Sponsor profile awaiting review',
        'review_requested_body' => ':sponsor changed public profile details.',
        'review_approved_title' => 'Sponsor profile approved',
        'review_approved_body' => 'The public profile for :sponsor has been approved.',
        'review_rejected_title' => 'Sponsor profile not approved',
        'review_rejected_body' => 'The profile for :sponsor needs changes. Open the cockpit to read the review note.',
    ],
    'partnership' => [
        'requested' => 'Partnership request sent.',
        'withdrawn' => 'Partnership request withdrawn.',
        'duplicate' => 'An open or active partnership already exists for this club.',
        'withdraw_unavailable' => 'Only open partnership requests can be withdrawn.',
        'already_decided' => 'This partnership request has already been decided.',
        'decided' => 'Partnership request processed.',
        'notification_title' => 'New partnership request',
        'notification_body' => ':sponsor wants to partner with :club.',
        'approved_title' => 'Partnership request approved',
        'approved_body' => ':club approved the partnership request.',
        'rejected_title' => 'Partnership request rejected',
        'rejected_body' => ':club rejected the partnership request.',
    ],
];
