<?php

return [
    'flash' => [
        'created' => 'Position created.',
        'updated' => 'Position updated.',
        'deleted' => 'Position deleted.',
        'interest_sent' => 'Your expression of interest has been sent.',
    ],
    'pipeline' => [
        'updated' => 'Application status saved.',
        'erased' => 'The application and contact details were deleted.',
    ],
    'validation' => [
        'profile_login_required' => 'Sign in to share profile data for this application.',
        'profile_consent_required' => 'Confirm the purpose-limited profile sharing.',
        'invalid_transition' => 'This status change is not allowed in the recruiting workflow.',
        'chat_not_available' => 'Application chat requires a linked account and explicit contact consent.',
    ],
    'chat' => [
        'name' => 'Application: :title',
        'description' => 'Protected application chat. Share only information needed for this process.',
    ],
    'notifications' => [
        'chat_title' => 'Application chat opened',
        'chat_body' => 'The club started a chat about your application for “:title”.',
        'offer_title' => 'An offer for your application',
        'offer_body' => ':club made you an offer for “:title”. You can now open the membership process.',
        'hired_title' => 'Your application was successful',
        'hired_body' => ':club accepted your application for “:title”. Complete the membership process now.',
    ],
    'mail' => [
        'subject' => 'New expression of interest: :title',
        'greeting' => 'Hello,',
        'intro' => 'A new expression of interest was submitted for “:title” at :club.',
        'name' => 'Name: :name',
        'email' => 'Email: :email',
        'phone' => 'Phone: :phone',
        'message' => 'Message: :message',
        'action' => 'Open jobs page',
    ],
];
