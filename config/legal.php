<?php

return [
    'provider_name' => env('LEGAL_PROVIDER_NAME', env('APP_NAME', 'Airmius')),
    'street' => env('LEGAL_STREET', ''),
    'city' => env('LEGAL_CITY', ''),
    'country' => env('LEGAL_COUNTRY', 'Deutschland'),
    'email' => env('LEGAL_EMAIL', env('MAIL_FROM_ADDRESS', 'support@airmius.com')),
    'support_email' => env('LEGAL_SUPPORT_EMAIL', env('LEGAL_EMAIL', env('MAIL_FROM_ADDRESS', 'support@airmius.com'))),
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', 'datenschutz@airmius.com'),
    'legal_email' => env('LEGAL_LEGAL_EMAIL', 'legal@airmius.com'),
    'phone' => env('LEGAL_PHONE', 'Telefon auf Anfrage'),
    'representative' => env('LEGAL_REPRESENTATIVE', env('LEGAL_PROVIDER_NAME', env('APP_NAME', 'Airmius'))),
    'register' => env('LEGAL_REGISTER', 'Kein Registereintrag angegeben.'),
    'vat_id' => env('LEGAL_VAT_ID', 'Keine Umsatzsteuer-ID angegeben.'),
    'supervisory_authority' => env('LEGAL_SUPERVISORY_AUTHORITY', 'Keine besondere Aufsichtsbehoerde angegeben.'),
    'content_responsible' => env('LEGAL_CONTENT_RESPONSIBLE', env('LEGAL_PROVIDER_NAME', env('APP_NAME', 'Airmius')).', '.env('LEGAL_COUNTRY', 'Deutschland')),
];
