<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_backup' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_BACKUP_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_BACKUP_URL'),
            'host' => env('MAIL_BACKUP_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_BACKUP_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_BACKUP_USERNAME'),
            'password' => env('MAIL_BACKUP_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_support' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SUPPORT_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_SUPPORT_URL'),
            'host' => env('MAIL_SUPPORT_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_SUPPORT_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_SUPPORT_USERNAME'),
            'password' => env('MAIL_SUPPORT_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_marketplace' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_MARKETPLACE_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_MARKETPLACE_URL'),
            'host' => env('MAIL_MARKETPLACE_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_MARKETPLACE_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_MARKETPLACE_USERNAME'),
            'password' => env('MAIL_MARKETPLACE_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_academy' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_ACADEMY_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_ACADEMY_URL'),
            'host' => env('MAIL_ACADEMY_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_ACADEMY_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_ACADEMY_USERNAME'),
            'password' => env('MAIL_ACADEMY_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_security' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SECURITY_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_SECURITY_URL'),
            'host' => env('MAIL_SECURITY_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_SECURITY_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_SECURITY_USERNAME'),
            'password' => env('MAIL_SECURITY_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_partners' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_PARTNERS_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_PARTNERS_URL'),
            'host' => env('MAIL_PARTNERS_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_PARTNERS_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_PARTNERS_USERNAME'),
            'password' => env('MAIL_PARTNERS_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'smtp_legal' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_LEGAL_SCHEME', env('MAIL_SCHEME')),
            'url' => env('MAIL_LEGAL_URL'),
            'host' => env('MAIL_LEGAL_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_LEGAL_PORT', env('MAIL_PORT', 2525)),
            'username' => env('MAIL_LEGAL_USERNAME'),
            'password' => env('MAIL_LEGAL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'smtp_backup',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
