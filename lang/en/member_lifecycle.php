<?php

return [
    'rules' => [
        'inactive' => ['month' => '12 months', 'title' => 'Mark as inactive', 'description' => 'The user is retained but is clearly identified as inactive in the admin area.'],
        'reactivation' => ['month' => '18 months', 'title' => 'Send reactivation email', 'description' => 'Send an automatic or manual reminder and log delivery in the mail center.'],
        'hidden' => ['month' => '24 months', 'title' => 'Hide profile', 'description' => 'Disable the public profile, search visibility and non-essential communication.'],
        'anonymized' => ['month' => '36 months', 'title' => 'Anonymize', 'description' => 'Remove or anonymize personal data that is no longer required.'],
        'archived' => ['month' => 'Required records', 'title' => 'Archive separately', 'description' => 'Invoices, payments and contract records remain available for statutory retention periods.'],
    ],
];
