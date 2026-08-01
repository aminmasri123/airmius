<?php

return [
    'mobile_app_url' => env('AIRMIUS_MOBILE_APP_URL', 'https://app.airmius.com'),
    'verification_url' => env('AIRMIUS_VERIFICATION_URL', env('APP_URL', 'http://localhost')),

    'billing' => [
        'company_name' => env('AIRMIUS_BILLING_COMPANY_NAME', 'Airmius'),
        'legal_name' => env('AIRMIUS_BILLING_LEGAL_NAME', ''),
        'street' => env('AIRMIUS_BILLING_STREET', ''),
        'postal_code' => env('AIRMIUS_BILLING_POSTAL_CODE', ''),
        'city' => env('AIRMIUS_BILLING_CITY', ''),
        'country' => env('AIRMIUS_BILLING_COUNTRY', 'Deutschland'),
        'email' => env('AIRMIUS_BILLING_EMAIL', ''),
        'website' => env('AIRMIUS_BILLING_WEBSITE', 'airmius.com'),
        'tax_number' => env('AIRMIUS_BILLING_TAX_NUMBER', ''),
        'vat_id' => env('AIRMIUS_BILLING_VAT_ID', ''),
        'court' => env('AIRMIUS_BILLING_COURT', ''),
        'registration_number' => env('AIRMIUS_BILLING_REGISTRATION_NUMBER', ''),
        'managing_director' => env('AIRMIUS_BILLING_MANAGING_DIRECTOR', ''),
        'small_business_notice' => env('AIRMIUS_BILLING_SMALL_BUSINESS_NOTICE', ''),
        'invoice_note' => env('AIRMIUS_BILLING_INVOICE_NOTE', ''),
    ],
];
