<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local Mail Safety
    |--------------------------------------------------------------------------
    |
    | Local development should not damage the reputation of real mailboxes.
    | Keep real SMTP disabled locally unless a developer explicitly opts in.
    |
    */

    'allow_real_mail_in_local' => env('MAIL_ALLOW_REAL_IN_LOCAL', false),

    'require_2fa_for_secret_changes' => env('MAIL_REQUIRE_2FA_FOR_SECRET_CHANGES', true),

    /*
    |--------------------------------------------------------------------------
    | Transactional Senders
    |--------------------------------------------------------------------------
    |
    | Categories let the app choose the right mailbox for the job without
    | spreading sender decisions across controllers and notifications.
    |
    */

    'senders' => [
        'system' => [
            'mailer' => env('MAIL_SYSTEM_MAILER', 'smtp_support'),
            'address' => env('MAIL_SYSTEM_FROM_ADDRESS', 'support@airmius.com'),
            'name' => env('MAIL_SYSTEM_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'billing' => [
            'mailer' => env('MAIL_BILLING_MAILER', 'smtp_backup'),
            'address' => env('MAIL_BILLING_FROM_ADDRESS', env('MAIL_BACKUP_USERNAME', 'billing@airmius.com')),
            'name' => env('MAIL_BILLING_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'support' => [
            'mailer' => env('MAIL_SUPPORT_MAILER', 'smtp_support'),
            'address' => env('MAIL_SUPPORT_FROM_ADDRESS', 'support@airmius.com'),
            'name' => env('MAIL_SUPPORT_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'marketplace' => [
            'mailer' => env('MAIL_MARKETPLACE_MAILER', 'smtp_marketplace'),
            'address' => env('MAIL_MARKETPLACE_FROM_ADDRESS', 'marketplace@airmius.com'),
            'name' => env('MAIL_MARKETPLACE_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'academy' => [
            'mailer' => env('MAIL_ACADEMY_MAILER', 'smtp_academy'),
            'address' => env('MAIL_ACADEMY_FROM_ADDRESS', 'academy@airmius.com'),
            'name' => env('MAIL_ACADEMY_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'security' => [
            'mailer' => env('MAIL_SECURITY_MAILER', 'smtp_security'),
            'address' => env('MAIL_SECURITY_FROM_ADDRESS', 'security@airmius.com'),
            'name' => env('MAIL_SECURITY_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'partners' => [
            'mailer' => env('MAIL_PARTNERS_MAILER', 'smtp_partners'),
            'address' => env('MAIL_PARTNERS_FROM_ADDRESS', 'partners@airmius.com'),
            'name' => env('MAIL_PARTNERS_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],

        'legal' => [
            'mailer' => env('MAIL_LEGAL_MAILER', 'smtp_legal'),
            'address' => env('MAIL_LEGAL_FROM_ADDRESS', 'legal@airmius.com'),
            'name' => env('MAIL_LEGAL_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Airmius'))),
        ],
    ],

    'invoice_primary_category' => env('MAIL_INVOICE_PRIMARY_CATEGORY', 'billing'),
    'invoice_fallback_category' => env('MAIL_INVOICE_FALLBACK_CATEGORY', 'support'),

    'throttle_seconds' => [
        'invoice_created' => env('MAIL_THROTTLE_INVOICE_CREATED_SECONDS', 21600),
        'invoice_status_updated' => env('MAIL_THROTTLE_INVOICE_STATUS_SECONDS', 3600),
    ],
];
