<?php

namespace App\Services;

use App\Models\Setting;

class AirmiusLegalProfile
{
    private const PLACEHOLDER = 'BITTE NACH FIRMENANMELDUNG PFLEGEN';

    public function data(): array
    {
        $brandName = Setting::valueFor('billing_brand_name');
        if (! filled($brandName)) {
            $brandName = Setting::valueFor('billing_company_name', 'airmius.billing.company_name', 'Airmius');
        }

        return [
            'company_name' => $this->value('billing_company_name', 'airmius.billing.company_name', 'Airmius'),
            'brand_name' => (string) $brandName,
            'legal_name' => $this->value('billing_legal_name', 'airmius.billing.legal_name'),
            'street' => $this->value('billing_company_street', 'airmius.billing.street'),
            'postal_code' => $this->value('billing_company_postal_code', 'airmius.billing.postal_code'),
            'city' => $this->value('billing_company_city', 'airmius.billing.city'),
            'country' => $this->value('billing_company_country', 'airmius.billing.country', 'Deutschland'),
            'email' => $this->value('billing_company_email', 'airmius.billing.email'),
            'website' => $this->value('billing_company_website', 'airmius.billing.website', 'airmius.com'),
            'tax_number' => $this->value('billing_tax_number', 'airmius.billing.tax_number'),
            'vat_id' => $this->value('billing_vat_id', 'airmius.billing.vat_id'),
            'court' => $this->value('billing_court', 'airmius.billing.court'),
            'registration_number' => $this->value('billing_registration_number', 'airmius.billing.registration_number'),
            'managing_director' => $this->value('billing_managing_director', 'airmius.billing.managing_director'),
            'small_business_notice' => $this->value('billing_small_business_notice', 'airmius.billing.small_business_notice', ''),
            'invoice_note' => $this->value('billing_invoice_note', 'airmius.billing.invoice_note', ''),
        ];
    }

    public function bank(): array
    {
        return [
            'holder' => $this->settingOrPlaceholder('billing_bank_account_holder', 'Airmius'),
            'bank_name' => $this->settingOrPlaceholder('billing_bank_name'),
            'iban' => $this->settingOrPlaceholder('billing_iban'),
            'bic' => $this->settingOrPlaceholder('billing_bic'),
        ];
    }

    private function value(string $settingKey, string $configKey, ?string $default = null): string
    {
        $value = Setting::valueFor($settingKey);

        if (filled($value)) {
            return (string) $value;
        }

        $value = config($configKey);

        if (filled($value)) {
            return (string) $value;
        }

        return $default ?? self::PLACEHOLDER;
    }

    private function settingOrPlaceholder(string $settingKey, ?string $default = null): string
    {
        $value = Setting::valueFor($settingKey);

        if (filled($value)) {
            return (string) $value;
        }

        return $default ?? self::PLACEHOLDER;
    }
}
