<?php

namespace App\Support\Validation;

use Illuminate\Validation\Rule;

class ClubProfileRules
{
    public static function store(): array
    {
        return [
            ...self::profile(),
            'is_official' => ['boolean'],
        ];
    }

    public static function update(): array
    {
        $rules = [
            ...self::profile(),
            ...self::legalMasterData(),
            ...self::contactMasterData(),
            ...self::branding(),
            'logo' => ['nullable', 'string', 'max:255'],
        ];

        foreach (['name', 'country'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }

    private static function profile(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'official_club_number' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'sepa_account_holder' => ['nullable', 'string', 'max:120'],
            'sepa_iban' => ['nullable', 'string', 'max:40'],
            'sepa_bic' => ['nullable', 'string', 'max:20'],
            'is_listed' => ['boolean'],
            'teams_are_listed' => ['boolean'],
            'members_can_post_to_club' => ['boolean'],
            'members_can_post_to_teams' => ['boolean'],
        ];
    }

    private static function legalMasterData(): array
    {
        return [
            'registry_authority' => ['nullable', 'string', 'max:160'],
            'registry_number' => ['nullable', 'string', 'max:80'],
            'federation_affiliations' => ['nullable', 'array', 'max:20'],
            'federation_affiliations.*.name' => ['required', 'string', 'max:160'],
            'federation_affiliations.*.member_number' => ['nullable', 'string', 'max:80'],
            'federation_affiliations.*.valid_from' => ['nullable', 'date_format:Y-m-d'],
            'federation_affiliations.*.valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:federation_affiliations.*.valid_from'],
            'tax_authority' => ['nullable', 'string', 'max:160'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'vat_id' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z]{2}[A-Za-z0-9 .\/-]+$/'],
            'tax_status' => ['nullable', Rule::in(['unknown', 'nonprofit', 'taxable', 'mixed'])],
            'tax_exemption_valid_until' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private static function contactMasterData(): array
    {
        return [
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() .\/-]+$/'],
            'website_url' => ['nullable', 'url:http,https', 'max:500'],
            'contact_details_public' => ['boolean'],
            'contact_persons' => ['nullable', 'array', 'max:20'],
            'contact_persons.*.name' => ['required', 'string', 'max:160'],
            'contact_persons.*.role' => ['nullable', 'string', 'max:160'],
            'contact_persons.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_persons.*.phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() .\/-]+$/'],
            'contact_persons.*.is_public' => ['required', 'boolean'],
        ];
    }

    private static function branding(): array
    {
        return [
            'brand_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'letterhead_settings' => ['nullable', 'array'],
            'letterhead_settings.show_logo' => ['required_with:letterhead_settings', 'boolean'],
            'letterhead_settings.header' => ['nullable', 'string', 'max:300'],
            'letterhead_settings.address_line' => ['nullable', 'string', 'max:300'],
            'letterhead_settings.footer' => ['nullable', 'string', 'max:500'],
            'document_templates' => [
                'nullable',
                'array',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $names = collect($value ?? [])->pluck('name')->map(fn ($name) => mb_strtolower(trim((string) $name)));
                    if ($names->duplicates()->isNotEmpty()) {
                        $fail(__('validation.distinct'));
                    }

                    $multipleDefaults = collect($value ?? [])->where('is_default', true)->groupBy('type')
                        ->contains(fn ($templates) => $templates->count() > 1);
                    if ($multipleDefaults) {
                        $fail(__('validation.distinct'));
                    }
                },
            ],
            'document_templates.*.name' => ['required', 'string', 'max:160'],
            'document_templates.*.type' => ['required', Rule::in(['letter', 'invoice', 'receipt', 'certificate', 'custom'])],
            'document_templates.*.header' => ['nullable', 'string', 'max:300'],
            'document_templates.*.footer' => ['nullable', 'string', 'max:500'],
            'document_templates.*.is_default' => ['required', 'boolean'],
        ];
    }
}
